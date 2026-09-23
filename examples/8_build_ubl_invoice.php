<?php

require_once("../vendor/autoload.php");

use Smart\Zatca\InvoiceBuilder;

$xml = InvoiceBuilder::simplified([
    'id' => 'SME-0001',
    'uuid' => 'a9ea6c06-1a83-432c-8854-dd023a1753d1',
    'issueDate' => '2026-09-23',
    'issueTime' => '10:00:00',
    'currency' => 'SAR',
    'icv' => 1,
    'pih' => 'NWZlY2ViNjZmZmM0NmYzOWQ3YzI5ZjMyYjI0M2Q4ZjBhN2Q5ZTUx',
    'seller' => [
        'name' => 'Acme Store',
        'vatNumber' => '310122393500003',
        'crNumber' => '1010010000',
        'street' => 'King Fahd Rd',
        'city' => 'Riyadh',
        'postalCode' => '11564',
        'countryCode' => 'SA',
    ],
    'lines' => [
        ['name' => 'Item A', 'quantity' => 2, 'unitPrice' => 100.0, 'vatPercent' => 15.0],
        ['name' => 'Item B', 'quantity' => 1, 'unitPrice' => 50.0, 'vatPercent' => 15.0, 'discountAmount' => 5.0],
    ],
]);

// For B2B clearance invoices add 'buyer' and use ::standard():
// $xml = InvoiceBuilder::standard([...$data, 'buyer' => ['name' => '...', 'vatNumber' => '...']]);
// Credit/debit notes via 'type' => 'credit' | 'debit' (383/381).

if (! is_dir('output')) {
    mkdir('output', 0777, true);
}
file_put_contents('output/unsigned_invoice.xml', $xml);
echo 'Unsigned UBL invoice written to output/unsigned_invoice.xml' . PHP_EOL;
