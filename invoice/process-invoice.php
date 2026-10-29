<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    require_once __DIR__ . '/../includes/auth.php';
} catch (Throwable $exception) {
    respond(500, array('success' => false, 'message' => 'Errore di avvio del servizio: ' . $exception->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Metodo non consentito']);
}

if (!isLoggedIn()) {
    respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    respond(403, ['success' => false, 'message' => 'Richiesta non autorizzata']);
}

$userId = currentUserId();
$clientId = currentSelectedClientId();
if ($userId === null || $clientId === null) {
    respond(400, ['success' => false, 'message' => 'Seleziona un cliente prima di salvare la fattura']);
}

$documentNumber = trim((string) ($_POST['document_number'] ?? ''));
$issueDate = (string) ($_POST['issue_date'] ?? '');
$dueDate = (string) ($_POST['due_date'] ?? '');
$causale = trim((string) ($_POST['causale'] ?? ''));
$currency = strtoupper(trim((string) ($_POST['divisa'] ?? '')));

// Legge il regime fiscale dal profilo fiscale dell'utente
$taxRegimeCode = '';
if ($userId !== null) {
    $stmt = $pdo->prepare('SELECT tax_regime FROM users_profiles WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    $taxRegimeCode = strtoupper((string) ($profile['tax_regime'] ?? ''));
}

$taxRegimeMap = [
    'RF19' => 'forfettario',
    'RF01' => 'ordinario',
    'REGIME FORFETTARIO' => 'forfettario',
    'REGIME ORDINARIO' => 'ordinario',
    'FORFETTARIO' => 'forfettario',
    'ORDINARIO' => 'ordinario',
];
$taxRegime = $taxRegimeMap[$taxRegimeCode] ?? null;

if ($taxRegime === null && isset($_POST['regime_fiscale'])) {
    $regimeInput = strtoupper(trim((string) ($_POST['regime_fiscale'] ?? '')));
    $taxRegime = $taxRegimeMap[$regimeInput] ?? null;
}

$bolloToClient = (int) ($_POST['bollo_to_client'] ?? 0);
$bolloAmount = 0.0;

function isValidDate(string $date): bool {
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    return $parsedDate !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date;
}

if (!preg_match('/^[A-Z]+-\d{4}-\d+$/', $documentNumber)) {
    respond(422, array('success' => false, 'message' => 'Numero documento non valido'));
}
if (!isValidDate($issueDate) || !isValidDate($dueDate) || $dueDate < $issueDate) {
    respond(422, array('success' => false, 'message' => 'Controlla le date del documento e della scadenza'));
}
if ($taxRegime === null) {
    respond(422, array('success' => false, 'message' => 'Regime fiscale non valido'));
}
if (strlen($causale) > 8000) {
    respond(422, ['success' => false, 'message' => 'La causale supera la lunghezza consentita']);
}

$descriptions = $_POST['descrizione'] ?? null;
$quantities = $_POST['quantita'] ?? null;
$unitPrices = $_POST['prezzo_unitario'] ?? null;
$vatRates = $_POST['aliquota_iva'] ?? null;
$natures = $_POST['natura'] ?? null;
$expenses = $_POST['spese_accessorie'] ?? null;
$legalReferences = $_POST['riferimento_normativo'] ?? null;
$lineArrays = [$descriptions, $quantities, $unitPrices, $vatRates, $natures, $expenses, $legalReferences];

if (count(array_filter($lineArrays, 'is_array')) !== count($lineArrays)) {
    respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi non validi']);
}

$lineCount = count($descriptions);
foreach ($lineArrays as $values) {
    if (count($values) !== $lineCount) {
        respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi incompleti']);
    }
}
if ($lineCount === 0) {
    respond(422, ['success' => false, 'message' => 'Aggiungi almeno un bene o servizio']);
}

try {
    $allowedRates = $taxRegime === 'forfettario' ? [0] : [4, 5, 10, 22];
    $allowedNatures = ['non_soggetta', 'esente', 'fuori_campo'];
    $lineItems = [];
    $invoiceTotal = 0.0;
    $taxableSum = 0.0;
    $expensesSum = 0.0;

    foreach ($descriptions as $index => $rawDescription) {
        $quantityRaw = (string) $quantities[$index];
        $unitPriceRaw = (string) $unitPrices[$index];
        $rate = filter_var((string) $vatRates[$index], FILTER_VALIDATE_INT);
        $nature = (string) $natures[$index];
        $expenseRaw = (string) $expenses[$index];
        $legalReference = trim((string) $legalReferences[$index]);

        if ($rawDescription === '' || strlen($rawDescription) > 8000 || strlen($legalReference) > 2000
            || !is_numeric($quantityRaw) || !is_finite((float) $quantityRaw) || (float) $quantityRaw < 0
            || !is_numeric($unitPriceRaw) || !is_finite((float) $unitPriceRaw) || (float) $unitPriceRaw < 0
            || $rate === false || !in_array($rate, $allowedRates, true)
            || !in_array($nature, $allowedNatures, true)
            || !is_numeric($expenseRaw) || !is_finite((float) $expenseRaw) || (float) $expenseRaw < 0
            || ($taxRegime === 'forfettario' && $nature !== 'non_soggetta')) {
            respond(422, ['success' => false, 'message' => 'Controlla i dati dei beni, delle aliquote e del riepilogo']);
        }

        $quantity = round((float) $quantityRaw, 2);
        $unitPrice = round((float) $unitPriceRaw, 2);
        $additionalExpenses = round((float) $expenseRaw, 2);
        $taxableAmount = round($quantity * $unitPrice, 2);
        $taxAmount = round($taxableAmount * ($rate / 100), 2);
        $lineTotal = round($taxableAmount + $taxAmount + $additionalExpenses, 2);
        $invoiceTotal = round($invoiceTotal + $lineTotal, 2);
        $taxableSum = round($taxableSum + $taxableAmount, 2);
        $expensesSum = round($expensesSum + $additionalExpenses, 2);

        $lineItems[] = [
            'description' => $rawDescription,
            'quantity' => number_format($quantity, 2, '.', ''),
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'vat_rate' => $rate,
            'nature' => $nature,
            'taxable_amount' => number_format($taxableAmount, 2, '.', ''),
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'additional_expenses' => number_format($additionalExpenses, 2, '.', ''),
            'legal_reference' => $legalReference,
            'line_total' => number_format($lineTotal, 2, '.', ''),
        ];
    }

    $clientCheck = $pdo->prepare('SELECT id FROM clients WHERE id = :client_id AND user_id = :user_id LIMIT 1');
    $clientCheck->execute([':client_id' => $clientId, ':user_id' => $userId]);
    if (!$clientCheck->fetchColumn()) {
        respond(403, ['success' => false, 'message' => 'Il cliente selezionato non è valido']);
    }

    $storageDirectory = dirname(__DIR__) . '/storage/invoices';
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('Could not create invoice storage directory');
    }

    $checkNumber = $pdo->prepare('SELECT 1 FROM invoices WHERE document_number = :document_number LIMIT 1');
    $insertInvoice = $pdo->prepare('INSERT INTO invoices (client_id, user_id, document_number, issue_date, due_date, currency, tax_regime, total, bollo, bollo_to_client) VALUES (:client_id, :user_id, :document_number, :issue_date, :due_date, :currency, :tax_regime, :total, :bollo, :bollo_to_client)');

    $xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';
    
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $root = $xml->createElement('Invoice');
    $root->setAttribute('documentNumber', $documentNumber);
    $xml->appendChild($root);
    
    $causaleElement = $xml->createElement('Causale');
    $causaleElement->appendChild($xml->createTextNode($causale));
    $root->appendChild($causaleElement);

    $items = $xml->createElement('Items');
    $root->appendChild($items);

    foreach ($lineItems as $lineItem) {
        $item = $xml->createElement('Item');
        $items->appendChild($item);
        foreach ($lineItem as $name => $value) {
            $element = $xml->createElement(str_replace('_', '', ucwords($name, '_')));
            $element->appendChild($xml->createTextNode((string) $value));
            $item->appendChild($element);
        }
    }

    $xmlContent = $xml->saveXML();
    if ($xmlContent === false) {
        throw new RuntimeException('Could not serialize invoice XML');
    }

    $file = @fopen($xmlPath, 'x');
    if ($file === false) {
        respond(409, ['success' => false, 'message' => 'Il file XML per questo numero documento esiste già']);
    }
    $written = fwrite($file, $xmlContent);
    fclose($file);
    if ($written !== strlen($xmlContent)) {
        @unlink($xmlPath);
        throw new RuntimeException('Could not write invoice XML');
    }
    @chmod($xmlPath, 0600);

    $pdo->beginTransaction();
    $insertInvoice->execute([
        ':client_id' => $clientId,
        ':user_id' => $userId,
        ':document_number' => $documentNumber,
        ':issue_date' => $issueDate,
        ':due_date' => $dueDate,
        ':currency' => $currency,
        ':tax_regime' => $taxRegime,
        ':total' => number_format($invoiceTotal, 2, '.', ''),
        ':bollo' => number_format($bolloAmount, 2, '.', ''),
        ':bollo_to_client' => $bolloToClient ? 1 : 0,
    ]);
    $invoiceId = (int) $pdo->lastInsertId();
    $pdo->commit();

    respond(201, [
        'success' => true,
        'invoice_id' => $invoiceId,
        'document_number' => $documentNumber,
        'xml_file' => $documentNumber . '.xml',
    ]);

} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($xmlPath) && is_file($xmlPath)) {
        @unlink($xmlPath);
    }
    error_log('Invoice save failed: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    respond(500, ['success' => false, 'message' => 'Errore durante il salvataggio: ' . $exception->getMessage()]);
}