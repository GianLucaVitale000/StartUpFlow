<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

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
        // Parse XML for items and cassa if available
        $items = [];
        $causale = '';
        $cassa = null;
        if (is_file($xmlPath)) {
            $xml = new DOMDocument();
            if (@$xml->load($xmlPath, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $causaleNode = $xml->getElementsByTagName('Causale')->item(0);
                if ($causaleNode instanceof DOMElement) {
                    $causale = $causaleNode->textContent;
                }
                $cassaNode = $xml->getElementsByTagName('Cassa')->item(0);
                if ($cassaNode instanceof DOMElement) {
                    $cassa = [];
                    foreach ($cassaNode->childNodes as $fieldNode) {
                        if ($fieldNode instanceof DOMElement) {
                            $cassa[$fieldNode->tagName] = $fieldNode->textContent;
                        }
                    }
                }
                $itemFields = ['Description', 'Quantity', 'UnitPrice', 'VatRate', 'Nature', 'TaxableAmount', 'TaxAmount', 'AdditionalExpenses', 'LegalReference', 'LineTotal'];
                foreach ($xml->getElementsByTagName('Item') as $itemNode) {
                    $item = [];
                    foreach ($itemNode->childNodes as $fieldNode) {
                        if ($fieldNode instanceof DOMElement && in_array($fieldNode->tagName, $itemFields, true)) {
                            $item[$fieldNode->tagName] = $fieldNode->textContent;
                        }
                    }
                    $items[] = $item;
                }
            }
        }

        $clientName = $invoice['client_type'] === 'company'
            ? $invoice['company_name']
            : trim($invoice['first_name'] . ' ' . $invoice['last_name']);

        // Render printable HTML invoice view which browser can print to PDF
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
                .details { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
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
                    <h1>Fattura Pro-Forma</h1>
                    <p><strong>N. Documento:</strong> <?php echo htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Data:</strong> <?php echo htmlspecialchars($invoice['issue_date'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Scadenza:</strong> <?php echo htmlspecialchars($invoice['due_date'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div>
                    <h3>Cliente:</h3>
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
                <?php if (!empty($invoice['bollo_to_client']) && (float) $invoice['bollo'] > 0): ?>
                    <div>
                        <span>Marca da bollo:</span>
                        <span><?php echo number_format((float) $invoice['bollo'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($cassa) && isset($cassa['ImportoContributoCassa'])): ?>
                    <div>
                        <span>Contributo Cassa:</span>
                        <span><?php echo number_format((float) $cassa['ImportoContributoCassa'], 2, ',', '.'); ?> <?php echo htmlspecialchars($invoice['currency'], ENT_QUOTES, 'UTF-8'); ?></span>
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
