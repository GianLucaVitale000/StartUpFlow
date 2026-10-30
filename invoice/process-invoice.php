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
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Throwable $exception) {
    respond(500, ['success' => false, 'message' => 'Errore di avvio del servizio: ' . $exception->getMessage()]);
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

use FatturaElettronicaPhp\FatturaElettronica\Address;
use FatturaElettronicaPhp\FatturaElettronica\Customer;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocument;
use FatturaElettronicaPhp\FatturaElettronica\DigitalDocumentInstance;
use FatturaElettronicaPhp\FatturaElettronica\Enums\DocumentType;
use FatturaElettronicaPhp\FatturaElettronica\Enums\PaymentMethod;
use FatturaElettronicaPhp\FatturaElettronica\Enums\PaymentTerm;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TaxRegime;
use FatturaElettronicaPhp\FatturaElettronica\Enums\TransmissionFormat;
use FatturaElettronicaPhp\FatturaElettronica\Enums\VatEligibility;
use FatturaElettronicaPhp\FatturaElettronica\Enums\VatNature;
use FatturaElettronicaPhp\FatturaElettronica\Line;
use FatturaElettronicaPhp\FatturaElettronica\SimplifiedLine;
use FatturaElettronicaPhp\FatturaElettronica\Fund;
use FatturaElettronicaPhp\FatturaElettronica\PaymentDetails;
use FatturaElettronicaPhp\FatturaElettronica\PaymentInfo;
use FatturaElettronicaPhp\FatturaElettronica\Supplier;
use FatturaElettronicaPhp\FatturaElettronica\Total;

// Caricamento profilo utente (Cedente)
$stmt = $pdo->prepare('SELECT * FROM users_profiles WHERE user_id = :user_id LIMIT 1');
$stmt->execute([':user_id' => $userId]);
$supplierProfile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplierProfile || empty($supplierProfile['vat_number']) || empty($supplierProfile['cf'])) {
    respond(422, ['success' => false, 'message' => 'Profilo fiscale incompleto. Configura Partita IVA e Codice Fiscale nelle impostazioni.']);
}

// Caricamento cliente (Cessionario)
$clientStmt = $pdo->prepare('SELECT * FROM clients WHERE id = :client_id AND user_id = :user_id LIMIT 1');
$clientStmt->execute([':client_id' => $clientId, ':user_id' => $userId]);
$client = $clientStmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    respond(403, ['success' => false, 'message' => 'Il cliente selezionato non è valido']);
}

// Controllo esclusione fatture verso PA (Codice destinatario di 6 caratteri o formato FPA12)
$sdiCode = trim((string) ($client['sdi_code'] ?? ''));
if (strlen($sdiCode) === 6 || strtolower((string) ($_POST['invoice_format'] ?? '')) === 'pa') {
    respond(422, ['success' => false, 'message' => 'Le fatture verso la Pubblica Amministrazione (PA) sono escluse e non supportate da questo sistema.']);
}

$documentNumber = trim((string) ($_POST['document_number'] ?? ''));
$issueDate = (string) ($_POST['issue_date'] ?? '');
$dueDate = (string) ($_POST['due_date'] ?? '');
$causale = trim((string) ($_POST['causale'] ?? ''));
$currency = strtoupper(trim((string) ($_POST['divisa'] ?? 'EUR')));
if ($currency === '') {
    $currency = 'EUR';
}

$invoiceTypeInput = strtolower(trim((string) ($_POST['invoice_type'] ?? 'ordinaria')));
$isSimplified = ($invoiceTypeInput === 'semplificata');

// Tax regime mapping
$taxRegimeCode = strtoupper((string) ($supplierProfile['tax_regime'] ?? 'RF01'));
$taxRegimeMap = [
    'RF19' => 'forfettario',
    'RF01' => 'ordinario',
    'REGIME FORFETTARIO' => 'forfettario',
    'REGIME ORDINARIO' => 'ordinario',
    'FORFETTARIO' => 'forfettario',
    'ORDINARIO' => 'ordinario',
];
$taxRegime = $taxRegimeMap[$taxRegimeCode] ?? 'ordinario';
$enumTaxRegime = ($taxRegime === 'forfettario') ? TaxRegime::RF19() : TaxRegime::RF01();

$bolloToClient = (int) ($_POST['bollo_to_client'] ?? 0);
$bolloAmount = 0.0;

// Cassa previdenziale
$cassaTipo = strtoupper(trim((string) ($_POST['cassa_tipo'] ?? '')));
$cassaAliquota = (float) ($_POST['cassa_aliquota'] ?? 0);
$cassaImponibile = (float) ($_POST['cassa_imponibile'] ?? 0);
$cassaAliquotaIva = (int) ($_POST['cassa_aliquota_iva'] ?? 0);
$cassaNatura = trim((string) ($_POST['cassa_natura'] ?? ''));
$cassaImporto = (float) ($_POST['cassa_importo'] ?? 0);
$cassaImposta = (float) ($_POST['cassa_imposta'] ?? 0);
$cassaRiferimento = trim((string) ($_POST['cassa_riferimento'] ?? ''));
$cassaRitenuta = (int) ($_POST['cassa_ritenuta'] ?? 0);

function isValidDate(string $date): bool {
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    return $parsedDate !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date;
}

function normalizeCountry(?string $country): string {
    $c = trim((string) $country);
    if ($c === '') {
        return 'IT';
    }
    $cUpper = strtoupper($c);
    if (strlen($cUpper) === 2) {
        return $cUpper;
    }
    
    static $countryMap = null;
    if ($countryMap === null) {
        $countryMap = [];
        $jsonPath = dirname(__DIR__) . '/assets/data/countries.json';
        if (is_file($jsonPath)) {
            $data = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($data)) {
                foreach ($data as $entry) {
                    if (isset($entry['code'], $entry['name'])) {
                        $countryMap[strtoupper($entry['name'])] = strtoupper($entry['code']);
                    }
                }
            }
        }
        $countryMap['ITALIA'] = 'IT';
        $countryMap['ITALY'] = 'IT';
    }

    return $countryMap[$cUpper] ?? 'IT';
}

if (!preg_match('/^[A-Z]+-\d{4}-\d+$/', $documentNumber)) {
    respond(422, ['success' => false, 'message' => 'Numero documento non valido']);
}
if (!isValidDate($issueDate) || !isValidDate($dueDate) || $dueDate < $issueDate) {
    respond(422, ['success' => false, 'message' => 'Controlla le date del documento e della scadenza']);
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

if (!is_array($descriptions) || count(array_filter($lineArrays, 'is_array')) !== count($lineArrays)) {
    respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi non validi']);
}

$lineCount = count($descriptions);
foreach ($lineArrays as $values) {
    if (!is_array($values) || count($values) !== $lineCount) {
        respond(422, ['success' => false, 'message' => 'Dati dei beni e servizi incompleti']);
    }
}
if ($lineCount === 0) {
    respond(422, ['success' => false, 'message' => 'Aggiungi almeno un bene o servizio']);
}

try {
    $allowedRates = $taxRegime === 'forfettario' ? [0] : [4, 5, 10, 22];
    $allowedNatures = ['non_soggetta', 'esente', 'fuori_campo', 'N1', 'N2.1', 'N2.2', 'N3.1', 'N3.2', 'N3.3', 'N3.4', 'N3.5', 'N3.6', 'N4', 'N5', 'N6.1', 'N6.2', 'N6.3', 'N6.4', 'N6.5', 'N6.6', 'N6.7', 'N6.8', 'N6.9'];
    
    $lineItems = [];
    $invoiceTotal = 0.0;
    $taxableSum = 0.0;
    $expensesSum = 0.0;

    foreach ($descriptions as $index => $rawDescription) {
        $quantityRaw = (string) $quantities[$index];
        $unitPriceRaw = (string) $unitPrices[$index];
        $rateInput = (string) $vatRates[$index];
        $rate = filter_var($rateInput, FILTER_VALIDATE_INT);
        $nature = trim((string) $natures[$index]);
        $expenseRaw = (string) $expenses[$index];
        $legalReference = trim((string) $legalReferences[$index]);

        if ($rawDescription === '' || strlen($rawDescription) > 8000 || strlen($legalReference) > 2000
            || !is_numeric($quantityRaw) || !is_finite((float) $quantityRaw) || (float) $quantityRaw < 0
            || !is_numeric($unitPriceRaw) || !is_finite((float) $unitPriceRaw) || (float) $unitPriceRaw < 0
            || $rate === false || !is_numeric($expenseRaw) || !is_finite((float) $expenseRaw) || (float) $expenseRaw < 0) {
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
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate' => $rate,
            'nature' => $nature !== '' ? $nature : 'N2.2',
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'additional_expenses' => $additionalExpenses,
            'legal_reference' => $legalReference,
            'line_total' => $lineTotal,
        ];
    }

    // Calcolo cassa previdenziale
    $cassaCalculatedAmount = 0.0;
    $cassaCalculatedTax = 0.0;
    if ($cassaTipo !== '' && $cassaImponibile > 0 && $cassaAliquota > 0) {
        $cassaCalculatedAmount = round($cassaImponibile * $cassaAliquota / 100, 2);
        if ($cassaAliquotaIva > 0 && $cassaCalculatedAmount > 0) {
            $cassaCalculatedTax = round($cassaCalculatedAmount * $cassaAliquotaIva / 100, 2);
        }
        if ($cassaImporto <= 0) {
            $cassaImporto = $cassaCalculatedAmount;
        }
        if ($cassaImposta <= 0) {
            $cassaImposta = $cassaCalculatedTax;
        }
    }

    // Bollo
    $bolloRequired = ($taxRegime === 'forfettario' && ($taxableSum + $cassaImporto) > 77.47);
    if ($bolloRequired) {
        if ($bolloToClient) {
            $bolloAmount = 2.00;
        }
    }

    $invoiceTotal = round($invoiceTotal + $cassaImporto + $cassaImposta + $bolloAmount, 2);

    // Controllo limite per fattura semplificata (<= 400 EUR)
    if ($isSimplified && $invoiceTotal > 400.00) {
        respond(422, ['success' => false, 'message' => 'Le fatture semplificate non possono superare l\'importo totale di 400,00 € (Art. 21-bis DPR 633/72)']);
    }

    // --- Costruzione Supplier (Cedente) ---
    $supplier = (new Supplier())
        ->setCountryCode(normalizeCountry($supplierProfile['vat_country'] ?? 'IT'))
        ->setVatNumber((string) ($supplierProfile['vat_number'] ?? ''))
        ->setFiscalCode((string) ($supplierProfile['cf'] ?? ''))
        ->setTaxRegime($enumTaxRegime)
        ->setAddress(
            (new Address())
                ->setStreet((string) ($supplierProfile['address'] ?? ''))
                ->setStreetNumber((string) ($supplierProfile['street_number'] ?? ''))
                ->setZip((string) ($supplierProfile['postal_code'] ?? '00100'))
                ->setCity((string) ($supplierProfile['city'] ?? ''))
                ->setState((string) ($supplierProfile['province'] ?? ''))
                ->setCountryCode(normalizeCountry($supplierProfile['country'] ?? 'IT'))
        );

    if (!empty($supplierProfile['company_name'])) {
        $supplier->setOrganization((string) $supplierProfile['company_name']);
    } else {
        $supplier->setName((string) ($supplierProfile['first_name'] ?? ''));
        $supplier->setSurname((string) ($supplierProfile['last_name'] ?? ''));
    }

    if (!empty($supplierProfile['phone'])) {
        $supplier->setPhone((string) $supplierProfile['phone']);
    }
    if (!empty($supplierProfile['contact_email'])) {
        $supplier->setEmail((string) $supplierProfile['contact_email']);
    }
    if (!empty($supplierProfile['rea_office']) && !empty($supplierProfile['rea_number'])) {
        $supplier->setReaOffice((string) $supplierProfile['rea_office']);
        $supplier->setReaNumber((string) $supplierProfile['rea_number']);
        if (!empty($supplierProfile['share_capital'])) {
            $supplier->setCapital((float) $supplierProfile['share_capital']);
        }
        if (!empty($supplierProfile['sole_shareholder'])) {
            $supplier->setAssociateType((string) $supplierProfile['sole_shareholder']);
        }
        if (!empty($supplierProfile['liquidation_status'])) {
            $supplier->setSettlementType((string) $supplierProfile['liquidation_status']);
        }
    }

    // --- Costruzione Customer (Cessionario) ---
    $isCompany = (($client['client_type'] ?? 'company') === 'company');
    $customer = (new Customer())
        ->setCountryCode(normalizeCountry($client['country'] ?? 'IT'))
        ->setVatNumber((string) ($client['vat_number'] ?? ''))
        ->setFiscalCode((string) ($client['cf'] ?? ''))
        ->setAddress(
            (new Address())
                ->setStreet((string) ($client['address'] ?? ''))
                ->setZip((string) ($client['postal_code'] ?? '00100'))
                ->setCity((string) ($client['city'] ?? ''))
                ->setState((string) ($client['state'] ?? ''))
                ->setCountryCode(normalizeCountry($client['country'] ?? 'IT'))
        );

    if ($isCompany) {
        $customer->setOrganization((string) ($client['company_name'] ?? ''));
    } else {
        $customer->setName((string) ($client['first_name'] ?? ''));
        $customer->setSurname((string) ($client['last_name'] ?? ''));
    }

    $customerSdi = trim((string) ($client['sdi_code'] ?? '0000000'));
    if ($customerSdi === '') {
        $customerSdi = '0000000';
    }
    $customerPec = trim((string) ($client['pec'] ?? ''));

    // --- Costruzione DigitalDocument Instance ---
    $documentInstance = (new DigitalDocumentInstance())
        ->setDocumentType($isSimplified ? DocumentType::TD07() : DocumentType::TD01())
        ->setCurrency($currency)
        ->setDocumentDate(new DateTime($issueDate))
        ->setDocumentNumber($documentNumber)
        ->setDocumentTotal($invoiceTotal)
        ->addDescription($causale !== '' ? $causale : 'Fattura n. ' . $documentNumber);

    if ($bolloRequired) {
        $documentInstance->setVirtualDuty('SI');
        $documentInstance->setVirtualDutyAmount(2.00);
    }

    if ($cassaTipo !== '' && $cassaImporto > 0) {
        $fund = (new Fund())
            ->setType($cassaTipo)
            ->setPercentage($cassaAliquota)
            ->setAmount($cassaImporto)
            ->setSubtotal($cassaImponibile > 0 ? $cassaImponibile : null)
            ->setTaxPercentage((float) $cassaAliquotaIva);
        if ($cassaRitenuta) {
            $fund->setDeduction(true);
        }
        if ($cassaNatura !== '') {
            $fund->setVatNature($cassaNatura);
        }
        if ($cassaRiferimento !== '') {
            $fund->setRepresentative($cassaRiferimento);
        }
        $documentInstance->addFund($fund);
    }

    if ($causale !== '') {
        // Aggiungi causale extra se presente
    }

    if ($isSimplified) {
        // Righe semplificate
        foreach ($lineItems as $li) {
            $sLine = (new SimplifiedLine())
                ->setDescription($li['description'])
                ->setTotal($li['line_total'])
                ->setTaxPercentage((float) $li['vat_rate'])
                ->setTaxAmount($li['tax_amount']);
            
            $natCode = $li['nature'];
            if ($natCode !== '' && class_exists(VatNature::class)) {
                try {
                    $sLine->setVatNature(new VatNature($natCode));
                } catch (Throwable $e) {
                    $sLine->setVatNature(VatNature::N2_2());
                }
            }
            if ($li['legal_reference'] !== '') {
                $sLine->setReference($li['legal_reference']);
            }
            $documentInstance->addSimplifiedLine($sLine);
        }
    } else {
        // Righe ordinarie
        foreach ($lineItems as $index => $li) {
            $regLine = (new Line())
                ->setNumber($index + 1)
                ->setDescription($li['description'])
                ->setQuantity($li['quantity'])
                ->setUnitPrice($li['unit_price'])
                ->setTotal($li['taxable_amount'])
                ->setTaxPercentage($li['vat_rate']);

            $natCode = $li['nature'];
            if ($natCode !== '') {
                try {
                    $regLine->setVatNature(new VatNature($natCode));
                } catch (Throwable $e) {
                    $regLine->setVatNature(VatNature::N2_2());
                }
            }
            if ($li['legal_reference'] !== '') {
                $regLine->setReference($li['legal_reference']);
            }
            $documentInstance->addLine($regLine);
        }

        // Totali riepilogo IVA ordinario
        $documentInstance->addTotal(
            (new Total())
                ->setTaxPercentage(0)
                ->setVatNature(VatNature::N2_2())
                ->setTotal($taxableSum)
                ->setTaxAmount(0.00)
                ->setTaxType(VatEligibility::I())
                ->setReference("Operazione senza applicazione dell'IVA ai sensi dell'art. 1, commi 54-89, L. 190/2014")
        );
    }

    // Informazioni di pagamento
    $ibanToUse = trim((string) ($supplierProfile['iban'] ?? ''));
    if ($ibanToUse === '') {
        $ibanToUse = 'IT60X0000000000000000000000';
    }

    $documentInstance->addPaymentInformations(
        (new PaymentInfo())
            ->setTerms(PaymentTerm::TP02())
            ->addDetails(
                (new PaymentDetails())
                    ->setMethod(PaymentMethod::MP05())
                    ->setDueDate(new DateTime($dueDate))
                    ->setAmount($invoiceTotal)
                    ->setIban($ibanToUse)
            )
    );

    // --- DigitalDocument (Root) ---
    $transmissionFormat = $isSimplified ? TransmissionFormat::FSM10() : TransmissionFormat::FPR12();
    $supplierVatNum = (string) $supplierProfile['vat_number'];

    $digitalDocument = (new DigitalDocument())
        ->setCountryCode('IT')
        ->setSenderVatId($supplierVatNum)
        ->setSendingId('00001')
        ->setTransmissionFormat($transmissionFormat)
        ->setCustomerSdiCode($customerSdi)
        ->setSupplier($supplier)
        ->setCustomer($customer)
        ->addDigitalDocumentInstance($documentInstance);

    if ($customerPec !== '') {
        $digitalDocument->setCustomerPec($customerPec);
    }

    // Validazione XSD della libreria
    $validator = $digitalDocument->validate();
    if (!$validator->isValid()) {
        $errorMessages = [];
        foreach ($validator->errors() as $err) {
            $errorMessages[] = is_array($err) ? implode(', ', $err) : (string)$err;
        }
        respond(422, [
            'success' => false,
            'message' => 'Errore di validazione XML Fattura Elettronica: ' . implode(' | ', $errorMessages)
        ]);
    }

    $storageDirectory = dirname(__DIR__) . '/storage/invoices';
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('Could not create invoice storage directory');
    }

    $xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';
    if (is_file($xmlPath)) {
        respond(409, ['success' => false, 'message' => 'Il file XML per questo numero documento esiste già']);
    }

    // Scrittura file XML tramite la libreria
    $digitalDocument->write($xmlPath, true);

    // Salvataggio nel database
    $pdo->beginTransaction();
    $insertInvoice = $pdo->prepare('INSERT INTO invoices (client_id, user_id, document_number, issue_date, due_date, currency, tax_regime, total, bollo, bollo_to_client, cassa_type, cassa_amount) VALUES (:client_id, :user_id, :document_number, :issue_date, :due_date, :currency, :tax_regime, :total, :bollo, :bollo_to_client, :cassa_type, :cassa_amount)');
    
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
        ':cassa_type' => $cassaTipo !== '' ? $cassaTipo : null,
        ':cassa_amount' => $cassaTipo !== '' ? number_format($cassaImporto, 2, '.', '') : 0.00,
    ]);
    $invoiceId = (int) $pdo->lastInsertId();
    $pdo->commit();

    respond(201, [
        'success' => true,
        'invoice_id' => $invoiceId,
        'document_number' => $documentNumber,
        'xml_file' => $documentNumber . '.xml',
        'is_simplified' => $isSimplified,
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
