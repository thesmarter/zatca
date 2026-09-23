<?php

namespace Zid\Zatca\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Zid\Zatca\InvoiceBuilder;

class UblInvoiceBuilderTest extends TestCase
{
    private function sampleData(): array
    {
        return [
            'id' => 'SME-0001',
            'uuid' => 'a9ea6c06-1a83-432c-8854-dd023a1753d1',
            'issueDate' => '2026-09-23',
            'issueTime' => '10:00:00',
            'currency' => 'SAR',
            'icv' => 1,
            'pih' => 'NWZlY2ViNjZmZmM0NmYzOWQ3YzI5ZjMyYjI0M2Q4ZjBhN2Q5ZTUx',
            'seller' => [
                'name' => 'Test Seller',
                'vatNumber' => '310122393500003',
                'crNumber' => '1010010000',
                'street' => 'Test St',
                'city' => 'Riyadh',
                'postalCode' => '11564',
                'countryCode' => 'SA',
            ],
            'lines' => [
                ['name' => 'Item A', 'quantity' => 2, 'unitPrice' => 100.0, 'vatPercent' => 15.0],
                ['name' => 'Item B', 'quantity' => 1, 'unitPrice' => 50.0, 'vatPercent' => 15.0, 'discountAmount' => 5.0],
            ],
        ];
    }

    public function testSimplifiedInvoiceBuildsWithCorrectTotals(): void
    {
        $xml = InvoiceBuilder::simplified($this->sampleData());

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'Builder output is not well-formed XML.');

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        // 2*100 + (1*50-5) = 245 net, 15% VAT = 36.75, payable = 281.75
        $this->assertSame('245.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount/parent::*/cbc:LineExtensionAmount)'));
        $this->assertSame('281.75', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $this->assertSame('36.75', $xpath->evaluate('string(//cac:TaxTotal/cbc:TaxAmount)'));
        $this->assertSame('5.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount)'));

        $this->assertSame('388', $xpath->evaluate('string(//cbc:InvoiceTypeCode)'));
        $this->assertSame('0200000', $xpath->evaluate('string(//cbc:InvoiceTypeCode/@name)'));
        $this->assertSame('reporting:1.0', $xpath->evaluate('string(//cbc:ProfileID)'));

        $this->assertSame(1, (int) $xpath->evaluate('count(//cac:AdditionalDocumentReference[cbc:ID="ICV"])'));
        $this->assertSame(1, (int) $xpath->evaluate('count(//cac:AdditionalDocumentReference[cbc:ID="PIH"])'));

        // Unsigned output must not contain signature artifacts.
        $this->assertSame(0, (int) $xpath->evaluate('count(//*[local-name()="UBLExtensions"])'));
        $this->assertSame(0, (int) $xpath->evaluate('count(//*[local-name()="Signature"])'));

        $this->assertSame(2, (int) $xpath->evaluate('count(//cac:InvoiceLine)'));
    }

    public function testStandardInvoiceRequiresBuyer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        InvoiceBuilder::standard($this->sampleData());
    }

    public function testCreditNoteTypeCode(): void
    {
        $data = $this->sampleData();
        $data['type'] = 'credit';

        $xml = InvoiceBuilder::simplified($data);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->assertSame('383', $xpath->evaluate('string(//cbc:InvoiceTypeCode)'));
        $this->assertSame('0201000', $xpath->evaluate('string(//cbc:InvoiceTypeCode/@name)'));
    }

    public function testEmptyLinesRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $data = $this->sampleData();
        $data['lines'] = [];

        InvoiceBuilder::simplified($data);
    }
}
