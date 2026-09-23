<?php

declare(strict_types=1);

namespace Smart\Zatca;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Builds unsigned ZATCA-compliant UBL 2.1 invoice XML from plain PHP arrays.
 *
 * The output contains no UBLExtensions, signature or QR nodes: those are
 * added later by InvoiceSigningService as part of hash -> sign -> submit.
 * Totals are computed from the lines; all amounts are rounded to 2 decimals.
 *
 * Data shape:
 * [
 *   'profile'   => 'simplified'|'standard' (default 'simplified'),
 *   'type'      => 'invoice'|'credit'|'debit' (default 'invoice'),
 *   'id'        => 'INV-0001',            // invoice number
 *   'uuid'      => '...',                 // v4 UUID string
 *   'issueDate' => 'Y-m-d', 'issueTime' => 'H:i:s',
 *   'currency'  => 'SAR',
 *   'icv'       => 1,                     // invoice counter per EGS device
 *   'pih'       => 'base64...',           // previous invoice hash
 *   'paymentMeansCode' => '10',           // UNCL 4461, default cash-like
 *   'seller'    => ['name','vatNumber','crNumber'?,address...],
 *   'buyer'     => [...]                  // required for standard
 *   'lines'     => [['name','quantity','unitPrice','vatPercent','discountAmount'?]...],
 * ]
 * Address shape: ['street','city','postalCode','countryCode' (default 'SA')].
 */
final class InvoiceBuilder
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const TYPE_CODES = [
        'invoice' => '388',
        'credit' => '383',
        'debit' => '381',
    ];

    private const TYPE_NAMES = [
        'standard:invoice' => '0100000',
        'standard:credit' => '0101000',
        'standard:debit' => '0102000',
        'simplified:invoice' => '0200000',
        'simplified:credit' => '0201000',
        'simplified:debit' => '0202000',
    ];

    public function __construct(private array $data = [])
    {
    }

    public function toXml(): string
    {
        return self::build($this->data);
    }

    public static function simplified(array $data): string
    {
        $data['profile'] = 'simplified';

        return self::build($data);
    }

    public static function standard(array $data): string
    {
        $data['profile'] = 'standard';

        return self::build($data);
    }

    public static function build(array $data): string
    {
        $profile = $data['profile'] ?? 'simplified';
        $type = $data['type'] ?? 'invoice';

        if (! in_array($profile, ['simplified', 'standard'], true)) {
            throw new InvalidArgumentException("Unknown invoice profile '{$profile}'.");
        }

        if (! isset(self::TYPE_CODES[$type])) {
            throw new InvalidArgumentException("Unknown invoice type '{$type}'.");
        }

        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            throw new InvalidArgumentException('At least one invoice line is required.');
        }

        $seller = $data['seller'] ?? [];
        foreach (['name', 'vatNumber'] as $field) {
            if (empty($seller[$field])) {
                throw new InvalidArgumentException("Seller '{$field}' is required.");
            }
        }

        $buyer = $data['buyer'] ?? [];
        if ($profile === 'standard' && empty($buyer['name'])) {
            throw new InvalidArgumentException('Buyer name is required for standard invoices.');
        }

        foreach (['id', 'uuid', 'issueDate', 'issueTime', 'icv', 'pih'] as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Invoice '{$field}' is required.");
            }
        }

        $currency = $data['currency'] ?? 'SAR';
        $typeCode = self::TYPE_CODES[$type];
        $typeName = self::TYPE_NAMES["{$profile}:{$type}"];

        $computed = self::computeTotals($lines);

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = false;

        $root = $dom->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $dom->appendChild($root);

        self::text($dom, $root, 'cbc:ProfileID', $data['profileId'] ?? 'reporting:1.0');
        self::text($dom, $root, 'cbc:ID', (string) $data['id']);
        self::text($dom, $root, 'cbc:UUID', (string) $data['uuid']);
        self::text($dom, $root, 'cbc:IssueDate', (string) $data['issueDate']);
        self::text($dom, $root, 'cbc:IssueTime', (string) $data['issueTime']);

        $typeNode = self::text($dom, $root, 'cbc:InvoiceTypeCode', $typeCode);
        $typeNode->setAttribute('name', $typeName);

        self::text($dom, $root, 'cbc:DocumentCurrencyCode', $currency);
        self::text($dom, $root, 'cbc:TaxCurrencyCode', $currency);

        self::reference($dom, $root, 'ICV', (string) $data['icv']);
        self::pihReference($dom, $root, (string) $data['pih']);

        $root->appendChild(self::supplierParty($dom, $seller));

        if ($buyer !== []) {
            $root->appendChild(self::customerParty($dom, $buyer));
        }

        $payment = $dom->createElement('cac:PaymentMeans');
        $payment->appendChild(self::text($dom, $payment, 'cbc:PaymentMeansCode', (string) ($data['paymentMeansCode'] ?? '10')));
        $root->appendChild($payment);

        $root->appendChild(self::taxTotal($dom, $computed, $currency));

        $monetary = $dom->createElement('cac:LegalMonetaryTotal');
        $monetary->appendChild(self::amount($dom, 'cbc:LineExtensionAmount', $computed['lineExtension'], $currency));
        $monetary->appendChild(self::amount($dom, 'cbc:TaxExclusiveAmount', $computed['lineExtension'], $currency));
        $monetary->appendChild(self::amount($dom, 'cbc:TaxInclusiveAmount', $computed['taxInclusive'], $currency));
        if ($computed['discountTotal'] > 0) {
            $monetary->appendChild(self::amount($dom, 'cbc:AllowanceTotalAmount', $computed['discountTotal'], $currency));
        }
        $monetary->appendChild(self::amount($dom, 'cbc:PayableAmount', $computed['taxInclusive'], $currency));
        $root->appendChild($monetary);

        foreach ($computed['lines'] as $index => $line) {
            $root->appendChild(self::invoiceLine($dom, $index + 1, $line, $currency));
        }

        $xml = $dom->saveXML($dom->documentElement);
        if ($xml === false) {
            throw new InvalidArgumentException('The invoice XML could not be serialized.');
        }

        return $xml;
    }

    /**
     * @return array{lineExtension: float, discountTotal: float, taxTotal: float, taxInclusive: float, lines: array, groups: array}
     */
    private static function computeTotals(array $lines): array
    {
        $computed = [];
        $lineExtension = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $groups = [];

        foreach ($lines as $line) {
            foreach (['name', 'quantity', 'unitPrice', 'vatPercent'] as $field) {
                if (! isset($line[$field])) {
                    throw new InvalidArgumentException("Invoice line '{$field}' is required.");
                }
            }

            $quantity = (float) $line['quantity'];
            $unit = (float) $line['unitPrice'];
            $discount = (float) ($line['discountAmount'] ?? 0.0);
            $net = round($quantity * $unit - $discount, 2);
            $vatPercent = (float) $line['vatPercent'];
            $tax = round($net * $vatPercent / 100, 2);

            $lineExtension += $net;
            $discountTotal += $discount;
            $taxTotal += $tax;

            $key = number_format($vatPercent, 2, '.', '');
            $groups[$key] ??= ['percent' => $vatPercent, 'taxable' => 0.0, 'tax' => 0.0];
            $groups[$key]['taxable'] += $net;
            $groups[$key]['tax'] += $tax;

            $computed[] = [
                'name' => (string) $line['name'],
                'id' => (string) ($line['id'] ?? ''),
                'quantity' => $quantity,
                'unitPrice' => $unit,
                'discount' => $discount,
                'net' => $net,
                'vatPercent' => $vatPercent,
                'tax' => $tax,
            ];
        }

        return [
            'lineExtension' => round($lineExtension, 2),
            'discountTotal' => round($discountTotal, 2),
            'taxTotal' => round($taxTotal, 2),
            'taxInclusive' => round($lineExtension + $taxTotal, 2),
            'lines' => $computed,
            'groups' => array_values($groups),
        ];
    }

    private static function supplierParty(DOMDocument $dom, array $seller): DOMElement
    {
        $party = $dom->createElement('cac:AccountingSupplierParty');
        $inner = $dom->createElement('cac:Party');

        $identification = $dom->createElement('cac:PartyIdentification');
        $id = self::text($dom, $identification, 'cbc:ID', (string) ($seller['crNumber'] ?? $seller['vatNumber']));
        $id->setAttribute('schemeID', isset($seller['crNumber']) ? 'CRN' : 'NAT');
        $inner->appendChild($identification);

        $inner->appendChild(self::address($dom, $seller));

        $taxScheme = $dom->createElement('cac:PartyTaxScheme');
        $taxScheme->appendChild(self::text($dom, $taxScheme, 'cbc:CompanyID', (string) $seller['vatNumber']));
        $scheme = $dom->createElement('cac:TaxScheme');
        $scheme->appendChild(self::text($dom, $scheme, 'cbc:ID', 'VAT'));
        $taxScheme->appendChild($scheme);
        $inner->appendChild($taxScheme);

        $legal = $dom->createElement('cac:PartyLegalEntity');
        $legal->appendChild(self::text($dom, $legal, 'cbc:RegistrationName', (string) $seller['name']));
        $inner->appendChild($legal);

        $party->appendChild($inner);

        return $party;
    }

    private static function customerParty(DOMDocument $dom, array $buyer): DOMElement
    {
        $party = $dom->createElement('cac:AccountingCustomerParty');
        $inner = $dom->createElement('cac:Party');

        if (! empty($buyer['vatNumber']) || ! empty($buyer['crNumber'])) {
            $identification = $dom->createElement('cac:PartyIdentification');
            $id = self::text($dom, $identification, 'cbc:ID', (string) ($buyer['vatNumber'] ?? $buyer['crNumber']));
            $id->setAttribute('schemeID', isset($buyer['vatNumber']) ? 'NAT' : 'CRN');
            $inner->appendChild($identification);
        }

        $inner->appendChild(self::address($dom, $buyer));

        $legal = $dom->createElement('cac:PartyLegalEntity');
        $legal->appendChild(self::text($dom, $legal, 'cbc:RegistrationName', (string) $buyer['name']));
        $inner->appendChild($legal);

        $party->appendChild($inner);

        return $party;
    }

    private static function address(DOMDocument $dom, array $data): DOMElement
    {
        $address = $dom->createElement('cac:PostalAddress');
        $address->appendChild(self::text($dom, $address, 'cbc:StreetName', (string) ($data['street'] ?? '')));
        $address->appendChild(self::text($dom, $address, 'cbc:CityName', (string) ($data['city'] ?? '')));
        $address->appendChild(self::text($dom, $address, 'cbc:PostalZone', (string) ($data['postalCode'] ?? '')));

        $country = $dom->createElement('cac:Country');
        $country->appendChild(self::text($dom, $country, 'cbc:IdentificationCode', (string) ($data['countryCode'] ?? 'SA')));
        $address->appendChild($country);

        return $address;
    }

    private static function taxTotal(DOMDocument $dom, array $computed, string $currency): DOMElement
    {
        $total = $dom->createElement('cac:TaxTotal');
        $total->appendChild(self::amount($dom, 'cbc:TaxAmount', $computed['taxTotal'], $currency));

        foreach ($computed['groups'] as $group) {
            $subtotal = $dom->createElement('cac:TaxSubtotal');
            $subtotal->appendChild(self::amount($dom, 'cbc:TaxableAmount', round($group['taxable'], 2), $currency));
            $subtotal->appendChild(self::amount($dom, 'cbc:TaxAmount', round($group['tax'], 2), $currency));

            $category = $dom->createElement('cac:TaxCategory');
            $category->appendChild(self::text($dom, $category, 'cbc:ID', $group['percent'] > 0 ? 'S' : 'Z'));
            $category->appendChild(self::text($dom, $category, 'cbc:Percent', self::fmt($group['percent'])));

            $scheme = $dom->createElement('cac:TaxScheme');
            $scheme->appendChild(self::text($dom, $scheme, 'cbc:ID', 'VAT'));
            $category->appendChild($scheme);

            $subtotal->appendChild($category);
            $total->appendChild($subtotal);
        }

        return $total;
    }

    private static function invoiceLine(DOMDocument $dom, int $position, array $line, string $currency): DOMElement
    {
        $node = $dom->createElement('cac:InvoiceLine');
        $node->appendChild(self::text($dom, $node, 'cbc:ID', $line['id'] !== '' ? $line['id'] : (string) $position));

        $quantity = self::text($dom, $node, 'cbc:InvoicedQuantity', self::fmt($line['quantity']));
        $quantity->setAttribute('unitCode', 'PCE');
        $node->appendChild($quantity);

        $node->appendChild(self::amount($dom, 'cbc:LineExtensionAmount', $line['net'], $currency));

        if ($line['discount'] > 0) {
            $allowance = $dom->createElement('cac:AllowanceCharge');
            $allowance->appendChild(self::text($dom, $allowance, 'cbc:ChargeIndicator', 'false'));
            $allowance->appendChild(self::text($dom, $allowance, 'cbc:AllowanceChargeReason', 'Discount'));
            $allowance->appendChild(self::amount($dom, 'cbc:Amount', $line['discount'], $currency));
            $node->appendChild($allowance);
        }

        $lineTax = $dom->createElement('cac:TaxTotal');
        $lineTax->appendChild(self::amount($dom, 'cbc:TaxAmount', $line['tax'], $currency));
        $node->appendChild($lineTax);

        $item = $dom->createElement('cac:Item');
        $item->appendChild(self::text($dom, $item, 'cbc:Name', $line['name']));

        $category = $dom->createElement('cac:ClassifiedTaxCategory');
        $category->appendChild(self::text($dom, $category, 'cbc:ID', $line['vatPercent'] > 0 ? 'S' : 'Z'));
        $category->appendChild(self::text($dom, $category, 'cbc:Percent', self::fmt($line['vatPercent'])));

        $scheme = $dom->createElement('cac:TaxScheme');
        $scheme->appendChild(self::text($dom, $scheme, 'cbc:ID', 'VAT'));
        $category->appendChild($scheme);
        $item->appendChild($category);
        $node->appendChild($item);

        $price = $dom->createElement('cac:Price');
        $price->appendChild(self::amount($dom, 'cbc:PriceAmount', round($line['unitPrice'], 2), $currency));
        $node->appendChild($price);

        return $node;
    }

    private static function reference(DOMDocument $dom, DOMElement $root, string $id, string $uuid): void
    {
        $reference = $dom->createElement('cac:AdditionalDocumentReference');
        $reference->appendChild(self::text($dom, $reference, 'cbc:ID', $id));
        $reference->appendChild(self::text($dom, $reference, 'cbc:UUID', $uuid));
        $root->appendChild($reference);
    }

    private static function pihReference(DOMDocument $dom, DOMElement $root, string $pih): void
    {
        $reference = $dom->createElement('cac:AdditionalDocumentReference');
        $reference->appendChild(self::text($dom, $reference, 'cbc:ID', 'PIH'));

        $attachment = $dom->createElement('cac:Attachment');
        $binary = self::text($dom, $attachment, 'cbc:EmbeddedDocumentBinaryObject', $pih);
        $binary->setAttribute('mimeCode', 'text/plain');
        $reference->appendChild($attachment);

        $root->appendChild($reference);
    }

    private static function text(DOMDocument $dom, DOMElement $parent, string $name, string $value): DOMElement
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);

        return $node;
    }

    private static function amount(DOMDocument $dom, string $name, float $value, string $currency): DOMElement
    {
        $node = $dom->createElement($name, self::fmt($value));
        $node->setAttribute('currencyID', $currency);

        return $node;
    }

    private static function fmt(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
