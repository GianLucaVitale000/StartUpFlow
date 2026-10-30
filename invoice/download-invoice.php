<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Non autenticato');
}

$userId = currentUserId();
$invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$format = strtolower(trim((string) (filter_input(INPUT_GET, 'format', FILTER_DEFAULT) ?? 'xml')));

if (!$invoiceId || !in_array($format, ['xml', 'pdf'], true)) {
    http_response_code(400);
    exit('Parametri non validi');
}

try {
    $stmt = $pdo->prepare(
        'SELECT i.*, c.client_type, c.company_name, c.first_name, c.last_name, 
                c.address, c.city, c.postal_code, c.state, c.country, c.cf, c.vat_number, c.pec, c.sdi_code
         FROM invoices i
         INNER JOIN clients c ON c.id = i.client_id
         WHERE i.id = :id AND i.user_id = :user_id LIMIT 1'
    );
    $stmt->execute([':id' => $invoiceId, ':user_id' => $userId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);
        exit('Fattura non trovata');
    }

    // Caricamento profilo utente (Cedente)
    $supplierStmt = $pdo->prepare('SELECT * FROM users_profiles WHERE user_id = :user_id LIMIT 1');
    $supplierStmt->execute([':user_id' => $userId]);
    $supplierProfile = $supplierStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $documentNumber = (string) $invoice['document_number'];
    $storageDirectory = __DIR__ . '/../storage/invoices';
    $xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';

    if ($format === 'xml') {
        if (!is_file($xmlPath)) {
            http_response_code(404);
            exit('File XML non trovato nello storage');
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $documentNumber . '.xml"');
        header('Content-Length: ' . (string) filesize($xmlPath));
        readfile($xmlPath);
        exit;
    }

    if ($format === 'pdf') {
        $items = [];
        $causale = '';
        $cassaAmount = 0.0;
        $virtualDuty = '';
        if (is_file($xmlPath)) {
            try {
                $eDoc = DigitalDocument::parseFrom($xmlPath);
                $instances = $eDoc->getDocumentInstances();
                if (!empty($instances)) {
                    $instance = $instances[0];
                    $descriptions = $instance->getDescriptions();
                    $causale = implode("\n", $descriptions);

                    $virtualDuty = $instance->getVirtualDuty();

                    $funds = $instance->getFunds();
                    if (!empty($funds)) {
                        $cassaAmount = (float) $funds[0]->getAmount();
                    }

                    if ($eDoc->isSimplified()) {
                        foreach ($instance->getSimplifiedLines() as $line) {
                            $items[] = [
                                'Description' => $line->getDescription(),
                                'Quantity' => '1',
                                'UnitPrice' => number_format((float)$line->getTotal(), 2, '.', ''),
                                'VatRate' => (string)($line->getTaxPercentage() ?? 0),
                                'LineTotal' => number_format((float)$line->getTotal(), 2, '.', ''),
                            ];
                        }
                    } else {
                        foreach ($instance->getLines() as $line) {
                            $items[] = [
                                'Description' => $line->getDescription(),
                                'Quantity' => number_format((float)$line->getQuantity(), 2, '.', ''),
                                'UnitPrice' => number_format((float)$line->getUnitPrice(), 2, '.', ''),
                                'VatRate' => (string)($line->getTaxPercentage() ?? 0),
                                'LineTotal' => number_format(round((float)$line->getTotal() * (1 + (float)($line->getTaxPercentage() ?? 0) / 100), 2), 2, '.', ''),
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {
                // Fallback if parsing fails
            }

            if (empty($items)) {
                $xml = new DOMDocument();
                if (@$xml->load($xmlPath, LIBXML_NONET | LIBXML_NOBLANKS)) {
                    $causaleNode = $xml->getElementsByTagName('Causale')->item(0);
                    if ($causaleNode instanceof DOMElement && $causale === '') {
                        $causale = $causaleNode->textContent;
                    }
                    $itemFields = ['Description', 'Quantity', 'UnitPrice', 'VatRate', 'Nature', 'TaxableAmount', 'TaxAmount', 'AdditionalExpenses', 'LegalReference', 'LineTotal'];
                    foreach ($xml->getElementsByTagName('Item') as $itemNode) {
                        $item = [];
                        foreach ($itemNode->childNodes as $fieldNode) {
                            if ($fieldNode instanceof DOMElement && in_array($fieldNode->tagName, $itemFields, true)) {
                                $item[$fieldNode->tagName] = $fieldNode->textContent;
                            }
                        }
                        if (!empty($item)) {
                            $items[] = [
                                'Description' => $item['Description'] ?? '',
                                'Quantity' => $item['Quantity'] ?? '1',
                                'UnitPrice' => $item['UnitPrice'] ?? '0',
                                'VatRate' => $item['VatRate'] ?? '0',
                                'LineTotal' => $item['LineTotal'] ?? '0',
                            ];
                        }
                    }
                }
            }
        }

        $clientName = $invoice['client_type'] === 'company'
            ? $invoice['company_name']
            : trim($invoice['first_name'] . ' ' . $invoice['last_name']);

        $supplierName = !empty($supplierProfile['company_name'])
            ? $supplierProfile['company_name']
            : trim(($supplierProfile['first_name'] ?? '') . ' ' . ($supplierProfile['last_name'] ?? ''));

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <title>Fattura <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; color: #111; line-height: 1.5; margin: 2rem; }
                h1, h2, h3 { margin: 0 0 0.5rem; }
                .header { display: flex; justify-content: space-between; margin-bottom: 2rem; border-bottom: 2px solid #eee; padding-bottom: 1rem; }
                .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; border-bottom: 2px solid #eee; padding-bottom: 1.5rem; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
                th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; font-size: 0.95rem; }
                th { background: #f8f9fa; font-weight: 600; }
                .totals { margin-left: auto; width: 300px; }
                .totals div { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid #eee; }
                .totals div.total { font-weight: bold; font-size: 1.1rem; border-top: 2px solid #333; border-bottom: none; margin-top: 0.5rem; padding-top: 0.5rem; }
                .print-bar { background: #111; color: #fff; padding: 1rem; text-align: center; margin-bottom: 2rem; border-radius: 8px; }
                .print-bar button { background: #fff; color: #111; border: none; padding: 0.5rem 1rem; font-weight: bold; cursor: pointer; border-radius: 4px; }
                @media print { .print-bar { display: none; } body { margin: 0; } }
            </style>
        </head>
        <body>
            <div class="print-bar">
                <span>Visualizzazione Fattura <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                <button onclick="window.print()">Salva come PDF / Stampa</button>
            </div>

            <div class="header">
                <div>
                    <h1>Fattura</h1>
                    <p><strong>N. Documento:</strong> <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Data:</strong> <?php echo htmlspecialchars($invoice['issue_date'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Scadenza:</strong> <?php echo htmlspecialchars($invoice['due_date'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="parties">
                <div>
                    <h3>Cedente (Prestatore):</h3>
                    <p><strong><?php echo htmlspecialchars($supplierName !== '' ? $supplierName : 'Cedente', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <?php if (!empty($supplierProfile['address'])): ?>
                        <?php echo htmlspecialchars($supplierProfile['address'] . (!empty($supplierProfile['street_number']) ? ' ' . $supplierProfile['street_number'] : ''), ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php echo htmlspecialchars(($supplierProfile['postal_code'] ?? '') . ' ' . ($supplierProfile['city'] ?? '') . ' (' . ($supplierProfile['province'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($supplierProfile['cf'])): ?>C.F.: <?php echo htmlspecialchars($supplierProfile['cf'], ENT_QUOTES, 'UTF-8'); ?><br><?php endif; ?>
                    <?php if (!empty($supplierProfile['vat_number'])): ?>P.IVA: <?php echo htmlspecialchars($supplierProfile['vat_number'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </p>
                </div>
                <div>
                    <h3>Cessionario (Cliente):</h3>
                    <p><strong><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <?php if (!empty($invoice['address'])): ?>
                        <?php echo htmlspecialchars($invoice['address'], ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php echo htmlspecialchars($invoice['postal_code'] . ' ' . $invoice['city'] . ' (' . ($invoice['state'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($invoice['cf'])): ?>C.F.: <?php echo htmlspecialchars($invoice['cf'], ENT_QUOTES, 'UTF-8'); ?><br><?php endif; ?>
                    <?php if (!empty($invoice['vat_number'])): ?>P.IVA: <?php echo htmlspecialchars($invoice['vat_number'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($causale)): ?>
                <div style="margin-bottom: 2rem;">
                    <h3>Causale</h3>
                    <p><?php echo nl2br(htmlspecialchars($causale, ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($virtualDuty === 'SI' || (float)$invoice['bollo'] > 0): ?>
                <div style="margin-bottom: 2rem; padding: 1rem; background: #f8f9fa; border-left: 4px solid #111; font-size: 0.9rem;">
                    <p><strong>Imposta di bollo assolta in modo virtuale ai sensi dell'art. 15 del D.P.R. 642/1972 e del D.M. 17/06/2014</strong></p>
                    <?php if (!empty($invoice['bollo_to_client']) && (float) $invoice['bollo'] > 0): ?>
                        <p>Importo bollo addebitato al cliente: <strong><?php echo number_format((float) $invoice['bollo'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Descrizione</th>
                        <th>Q.tà</th>
                        <th>Prezzo Unitario</th>
                        <th>IVA %</th>
                        <th>Totale Riga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['Description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['Quantity'] ?? '1', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float) ($item['UnitPrice'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['VatRate'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>%</td>
                            <td><?php echo number_format((float) ($item['LineTotal'] ?? 0), 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <?php if ($cassaAmount > 0): ?>
                    <div>
                        <span>Contributo Cassa:</span>
                        <span><?php echo number_format($cassaAmount, 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['bollo_to_client']) && (float) $invoice['bollo'] > 0): ?>
                    <div>
                        <span>Marca da bollo:</span>
                        <span><?php echo number_format((float) $invoice['bollo'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="total">
                    <span>Totale Fattura:</span>
                    <span><?php echo number_format((float) $invoice['total'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Errore durante il download: ' . $exception->getMessage());
}
