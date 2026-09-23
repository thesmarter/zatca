<?php

namespace Zid\Zatca\Tests;

use PHPUnit\Framework\TestCase;
use Zid\Zatca\Entities\CSID;
use Zid\Zatca\Exceptions\InvoiceSigningException;
use Zid\Zatca\GetDigitalSignatureService;
use Zid\Zatca\InvoiceSigningService;
use Zid\Zatca\QrCodeGeneratorService;

class SignInvoiceXmlTest extends TestCase
{
    private function signingService(string $fakeQr): InvoiceSigningService
    {
        $digitalSignature = new class() extends GetDigitalSignatureService {
            public function get(string $invoiceHash, string $privateKeyContent): string
            {
                return base64_encode('fake-signature-bytes');
            }
        };

        $qr = new class($fakeQr) extends QrCodeGeneratorService {
            public function __construct(private string $fakeQr)
            {
            }

            public function generate(CSID $csid, string $invoiceHash, string $canonicalXml, string $signatureValue): string
            {
                return $this->fakeQr;
            }
        };

        return new InvoiceSigningService($digitalSignature, $qr);
    }

    public function testSignInsertsExtensionFirstAndSignatureBeforeSupplier(): void
    {
        $canonicalXml = file_get_contents(__DIR__ . '/fixtures/unsigned_invoice.xml');
        $invoiceHash = base64_encode(hash('sha256', 'test-vector', true));
        $fakeQr = 'RkFLRV9RUg==';

        $csid = CSID::loadFromJson(__DIR__ . '/fixtures/ccsid.json');

        $result = $this->signingService($fakeQr)->sign($csid, 'unused-key', $canonicalXml, $invoiceHash);

        $decoded = base64_decode($result->b64SignedInvoice);
        $this->assertNotFalse($decoded);

        // Strip the prepended XML declaration the service adds.
        $decoded = preg_replace('/^<\?xml[^?]*\?>\s*/', '', $decoded);

        $dom = new \DOMDocument();
        $this->assertTrue(@$dom->loadXML($decoded), 'Signed output is not well-formed XML.');

        $this->assertSame('Invoice', $dom->documentElement->localName);

        // UBLExtensions must be the first element child of the root.
        $first = $dom->documentElement->firstChild;
        while ($first !== null && $first->nodeType !== XML_ELEMENT_NODE) {
            $first = $first->nextSibling;
        }
        $this->assertNotNull($first);
        $this->assertSame('UBLExtensions', $first->localName);

        // The invoice hash must land in the signature digest.
        $xpath = new \DOMXPath($dom);
        $digests = [];
        foreach ($xpath->query('//*[local-name()="DigestValue"]') as $node) {
            $digests[] = $node->textContent;
        }
        $this->assertContains($invoiceHash, $digests);

        // The QR payload must land in the attachment node.
        $this->assertStringContainsString($fakeQr, $decoded);

        // Signature block must precede the supplier party, as ZATCA requires.
        $sigPos = strpos($decoded, 'cac:Signature');
        $partyPos = strpos($decoded, 'cac:AccountingSupplierParty');
        $this->assertNotFalse($sigPos);
        $this->assertNotFalse($partyPos);
        $this->assertLessThan($partyPos, $sigPos);
    }

    public function testSignRejectsInvoiceWithoutSupplierParty(): void
    {
        $this->expectException(InvoiceSigningException::class);

        $canonicalXml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><cbc:ID xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">1</cbc:ID></Invoice>';

        $csid = CSID::loadFromJson(__DIR__ . '/fixtures/ccsid.json');

        $this->signingService('RkFLRV9RUg==')->sign($csid, 'unused-key', $canonicalXml, base64_encode('x'));
    }
}
