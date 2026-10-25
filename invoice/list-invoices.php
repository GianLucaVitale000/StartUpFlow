<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $data): void {
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	$respond(405, ['success' => false, 'message' => 'Metodo non consentito']);
}

if (!isLoggedIn()) {
	$respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

$userId = currentUserId();
if ($userId === null) {
	$respond(401, ['success' => false, 'message' => 'Utente non autenticato']);
}

try {
	$statement = $pdo->prepare(
		' SELECT i.id, i.document_number, i.issue_date, i.due_date, i.currency,
		        i.tax_regime, i.total, i.bollo, i.bollo_to_client, i.is_paid, c.client_type, c.company_name,
		        c.first_name, c.last_name
		 FROM invoices i
		 INNER JOIN clients c ON c.id = i.client_id
		 WHERE i.user_id = :user_id
		 ORDER BY i.issue_date DESC, i.id DESC'
	);
	$statement->execute([':user_id' => $userId]);
	$invoices = [];

	// Nomi leggibili delle casse previdenziali (se il catalogo non è leggibile si mostra solo il codice)
	$cassaNames = [];
	$cassaJson = @file_get_contents(dirname(__DIR__) . '/assets/data/casse-previdenziali.json');
	$cassaCatalog = $cassaJson === false ? null : json_decode($cassaJson, true);
	if (is_array($cassaCatalog)) {
		foreach ($cassaCatalog as $cassaEntry) {
			if (isset($cassaEntry['value'], $cassaEntry['name'])) {
				$cassaNames[(string) $cassaEntry['value']] = (string) $cassaEntry['name'];
			}
		}
	}
	$cassaFields = [
		'TipoCassa' => 'type',
		'AlCassa' => 'rate',
		'ImportoContributoCassa' => 'amount',
		'ImponibileCassa' => 'taxable_amount',
		'AliquotaIVA' => 'vat_rate',
		'Ritenuta' => 'withholding',
		'Natura' => 'nature',
		'RiferimentoAmministrazione' => 'reference',
	];
	$storageDirectory = dirname(__DIR__) . '/storage/invoices';
	$itemFields = [
		'Description' => 'description',
		'Quantity' => 'quantity',
		'UnitPrice' => 'unit_price',
		'VatRate' => 'vat_rate',
		'Nature' => 'nature',
		'TaxableAmount' => 'taxable_amount',
		'TaxAmount' => 'tax_amount',
		'AdditionalExpenses' => 'additional_expenses',
		'LegalReference' => 'legal_reference',
		'LineTotal' => 'line_total',
	];

	foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $invoice) {
		$clientName = $invoice['client_type'] === 'company'
			? $invoice['company_name']
			: trim($invoice['first_name'] . ' ' . $invoice['last_name']);
		$invoice['client_name'] = $clientName !== '' ? $clientName : 'Cliente';
		$invoice['is_paid'] = (bool) $invoice['is_paid'];
		$invoice['bollo_to_client'] = (bool) $invoice['bollo_to_client'];
		$invoice['items'] = [];
		$invoice['causale'] = '';
		$invoice['xml_available'] = false;
		$invoice['cassa'] = null;
		$documentNumber = (string) $invoice['document_number'];

		if (preg_match('/^SF-[A-F0-9]{16}$/', $documentNumber)) {
			$xmlPath = $storageDirectory . '/' . $documentNumber . '.xml';
			$fileSize = is_file($xmlPath) ? filesize($xmlPath) : false;
			if ($fileSize !== false && $fileSize <= 2_000_000) {
				$xml = new DOMDocument();
				if (@$xml->load($xmlPath, LIBXML_NONET | LIBXML_NOBLANKS)) {
					$invoice['xml_available'] = true;
					$causaleNode = $xml->getElementsByTagName('Causale')->item(0);
					if ($causaleNode instanceof DOMElement) {
						$invoice['causale'] = $causaleNode->textContent;
					}
					$cassaNode = $xml->getElementsByTagName('Cassa')->item(0);
					if ($cassaNode instanceof DOMElement) {
						$cassa = [];
						foreach ($cassaNode->childNodes as $fieldNode) {
							if ($fieldNode instanceof DOMElement && isset($cassaFields[$fieldNode->tagName])) {
								$cassa[$cassaFields[$fieldNode->tagName]] = $fieldNode->textContent;
							}
						}
						if (isset($cassa['type'])) {
							$cassa['type_label'] = $cassaNames[$cassa['type']] ?? $cassa['type'];
						}
						if (isset($cassa['amount'], $cassa['vat_rate'])) {
							$cassa['tax_amount'] = number_format(round((float) $cassa['amount'] * (float) $cassa['vat_rate'] / 100, 2), 2, '.', '');
						}
						$invoice['cassa'] = $cassa;
					}
					foreach ($xml->getElementsByTagName('Item') as $itemNode) {
						$item = [];
						foreach ($itemNode->childNodes as $fieldNode) {
							if ($fieldNode instanceof DOMElement && isset($itemFields[$fieldNode->tagName])) {
								$item[$itemFields[$fieldNode->tagName]] = $fieldNode->textContent;
							}
						}
						$invoice['items'][] = $item;
					}
				}
			}
		}

		unset($invoice['client_type'], $invoice['company_name'], $invoice['first_name'], $invoice['last_name']);
		$invoices[] = $invoice;
	}

	$respond(200, ['success' => true, 'invoices' => $invoices]);
} catch (Throwable $exception) {
	error_log('Invoice list failed: ' . $exception->getMessage());
	$respond(500, ['success' => false, 'message' => 'Errore durante il caricamento delle fatture']);
}