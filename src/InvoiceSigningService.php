<?php

declare(strict_types=1);

namespace Zid\Zatca;

use DateTime;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Zid\Zatca\Entities\CSID;
use Zid\Zatca\Entities\InvoiceSigningResult;
use Zid\Zatca\Exceptions\InvoiceSigningException;

class InvoiceSigningService
{
    public function __construct(
        private GetDigitalSignatureService $digitalSignatureService,
        private QrCodeGeneratorService $qrCodeGeneratorService,
    ) {
    }

    public function sign(CSID $csid, string $privateKeyContent, string $canonicalXml, string $invoiceHash): InvoiceSigningResult
    {
        $ublTemplatePath = __DIR__ . '/Data/ZatcaDataUbl.xml';
        $signaturePath = __DIR__ . '/Data/ZatcaDataSignature.xml';

        $x509CertificateContent = base64_decode($csid->certificate);
        $privateKeyContent = str_replace(["\n", "\t", "-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----"], '', $privateKeyContent);
        $signatureValue = $this->digitalSignatureService->get($invoiceHash, $privateKeyContent);

        // Generate QR Code
        $qrCode = $this->qrCodeGeneratorService->generate($csid, $invoiceHash, $canonicalXml, $signatureValue);

        $xmlDeclaration = '<?xml version="1.0" encoding="utf-8"?>';

        // Signing Simplified Invoice Document
        $signatureTimestamp = (new DateTime())->format('Y-m-d\TH:i:s');

        // Generate public key hashing
        $hashBytes = hash('sha256', $x509CertificateContent, true);
        $hashHex = bin2hex($hashBytes);
        $publicKeyHashing = base64_encode($hashHex);

        // Parse the X.509 certificate
        $parsedCertificate = openssl_x509_read("-----BEGIN CERTIFICATE-----\n" .
            chunk_split($x509CertificateContent, 64, "\n") .
            "-----END CERTIFICATE-----\n");

        // Extract certificate information
        $certInfo = openssl_x509_parse($parsedCertificate);
        $issuerName = $this->getIssuerName($certInfo);
        $serialNumber = $this->getSerialNumberForCertificateObject($certInfo);
        $signedPropertiesHash = $this->getSignedPropertiesHash($signatureTimestamp, $publicKeyHashing, $issuerName, $serialNumber);

        // Populate UBLExtension Template
        $stringUBLExtension = file_get_contents($ublTemplatePath);
        $stringUBLExtension = str_replace("INVOICE_HASH", $invoiceHash, $stringUBLExtension);
        $stringUBLExtension = str_replace("SIGNED_PROPERTIES", $signedPropertiesHash, $stringUBLExtension);
        $stringUBLExtension = str_replace("SIGNATURE_VALUE", $signatureValue, $stringUBLExtension);
        $stringUBLExtension = str_replace("CERTIFICATE_CONTENT", $x509CertificateContent, $stringUBLExtension);
        $stringUBLExtension = str_replace("SIGNATURE_TIMESTAMP", $signatureTimestamp, $stringUBLExtension);
        $stringUBLExtension = str_replace("PUBLICKEY_HASHING", $publicKeyHashing, $stringUBLExtension);
        $stringUBLExtension = str_replace("ISSUER_NAME", $issuerName, $stringUBLExtension);
        $stringUBLExtension = str_replace("SERIAL_NUMBER", $serialNumber, $stringUBLExtension);

        // Load the QR/signature template content.
        $stringSignature = file_get_contents($signaturePath);
        $stringSignature = str_replace('BASE64_QRCODE', $qrCode, $stringSignature);

        // Insert UBL extension as the first child of the invoice root and the
        // QR/signature block before the supplier party, using DOM so the
        // result never depends on exact whitespace or tag formatting.
        $updatedXmlString = $this->insertSignedFragments($canonicalXml, $stringUBLExtension, $stringSignature);

        $base64Invoice = base64_encode($xmlDeclaration . "\n" . $updatedXmlString);

        return new InvoiceSigningResult(
            signature: $signatureValue,
            b64SignedInvoice: $base64Invoice,
            b64QrCode: $qrCode,
        );
    }

    /**
     * Insert the populated UBLExtensions fragment as the first child of the
     * invoice root and the QR/signature fragment before the supplier party.
     *
     * The templates use the host document's namespace prefixes without
     * redeclaring them, so each fragment is parsed inside a wrapper that
     * declares the UBL namespaces and the resulting nodes are imported.
     *
     * @throws InvoiceSigningException
     */
    private function insertSignedFragments(string $canonicalXml, string $ublExtensionXml, string $signatureXml): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = true;

        if (@$dom->loadXML($canonicalXml) === false || $dom->documentElement === null) {
            throw new InvoiceSigningException('The canonical invoice XML could not be parsed.');
        }

        $root = $dom->documentElement;

        foreach ($this->parseFragment($ublExtensionXml) as $node) {
            $root->insertBefore($dom->importNode($node, true), $root->firstChild);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $parties = $xpath->query('//cac:AccountingSupplierParty');
        if ($parties === false || $parties->length === 0) {
            $parties = $xpath->query('//*[local-name()="AccountingSupplierParty"]');
        }

        if ($parties === false || $parties->length === 0) {
            throw new InvoiceSigningException('The <cac:AccountingSupplierParty> tag was not found in the XML.');
        }

        /** @var DOMNode $party */
        $party = $parties->item(0);
        $parent = $party->parentNode;
        if ($parent === null) {
            throw new InvoiceSigningException('The <cac:AccountingSupplierParty> tag has no parent node.');
        }

        foreach ($this->parseFragment($signatureXml) as $node) {
            $parent->insertBefore($dom->importNode($node, true), $party);
        }

        $out = $dom->saveXML($dom->documentElement);
        if ($out === false) {
            throw new InvoiceSigningException('The signed invoice XML could not be serialized.');
        }

        return $out;
    }

    /**
     * Parse an XML fragment (one or more top-level elements) by wrapping it
     * in a root that declares the UBL namespaces used by the templates.
     *
     * @return iterable<DOMNode>
     *
     * @throws InvoiceSigningException
     */
    private function parseFragment(string $fragmentXml): iterable
    {
        $wrapped = '<zatca-fragment'
            . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"'
            . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            . ' xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"'
            . '>' . $fragmentXml . '</zatca-fragment>';

        $tmp = new DOMDocument('1.0', 'utf-8');
        if (@$tmp->loadXML($wrapped) === false || $tmp->documentElement === null) {
            throw new InvoiceSigningException('A signature template fragment could not be parsed.');
        }

        $nodes = [];
        foreach ($tmp->documentElement->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $nodes[] = $child;
            }
        }

        return $nodes;
    }

    private function getIssuerName($certInfo) {
        $issuer = $certInfo['issuer'];

        if (isset($issuer['DC']) && is_array($issuer['DC'])) {
            $issuer['DC'] = array_reverse($issuer['DC']);
        }

        $issuerNameParts = [];
        if (!empty($issuer['CN'])) {
            $issuerNameParts[] = "CN=" . $issuer['CN'];
        }

        if (!empty($issuer['DC']) && is_array($issuer['DC'])) {
            foreach ($issuer['DC'] as $dc) {
                if (!empty($dc)) {
                    $issuerNameParts[] = "DC=" . $dc;
                }
            }
        }

        return implode(", ", $issuerNameParts);
    }

    private function getSerialNumberForCertificateObject($certInfo) {
        $serialNumberHex = $certInfo['serialNumberHex'];

        $serialNumberDec = '0';
        $hexLength = strlen($serialNumberHex);
        for ($i = 0; $i < $hexLength; $i++) {
            $hexDigit = hexdec($serialNumberHex[$i]);
            $serialNumberDec = bcmul($serialNumberDec, '16', 0);
            $serialNumberDec = bcadd($serialNumberDec, (string) $hexDigit, 0);
        }

        return $serialNumberDec;
    }

    private function getSignedPropertiesHash($signingTime, $digestValue, $x509IssuerName, $x509SerialNumber) {

        // Construct the XML string with exactly 36 spaces in front of <xades:SignedSignatureProperties>
        $xmlString = '<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">' . "\n" .
            '                                    <xades:SignedSignatureProperties>' . "\n" .
            '                                        <xades:SigningTime>' . $signingTime . '</xades:SigningTime>' . "\n" .
            '                                        <xades:SigningCertificate>' . "\n" .
            '                                            <xades:Cert>' . "\n" .
            '                                                <xades:CertDigest>' . "\n" .
            '                                                    <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' . "\n" .
            '                                                    <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $digestValue . '</ds:DigestValue>' . "\n" .
            '                                                </xades:CertDigest>' . "\n" .
            '                                                <xades:IssuerSerial>' . "\n" .
            '                                                    <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $x509IssuerName . '</ds:X509IssuerName>' . "\n" .
            '                                                    <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $x509SerialNumber . '</ds:X509SerialNumber>' . "\n" .
            '                                                </xades:IssuerSerial>' . "\n" .
            '                                            </xades:Cert>' . "\n" .
            '                                        </xades:SigningCertificate>' . "\n" .
            '                                    </xades:SignedSignatureProperties>' . "\n" .
            '                                </xades:SignedProperties>';

        $xmlString = str_replace("\r\n", "\n", $xmlString);
        $xmlString = trim($xmlString);

        $hashBytes = hash('sha256', $xmlString, true);

        $hashHex = bin2hex($hashBytes);

        return base64_encode($hashHex);
    }
}
