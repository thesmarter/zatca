<?php

declare(strict_types=1);

namespace Zid\Zatca;

use Zid\Zatca\Exceptions\InvoiceSigningException;

class GetDigitalSignatureService
{
    public function get(string $invoiceHash, string $privateKeyContent): string
    {
        $hashBytes = base64_decode($invoiceHash);

        if ($hashBytes === false) {
            throw new InvoiceSigningException('Failed to decode the base64-encoded XML hashing.');
        }

        $privateKeyContent = str_replace(["\n", "\t"], '', $privateKeyContent);

        if (strpos($privateKeyContent, "-----BEGIN EC PRIVATE KEY-----") === false &&
            strpos($privateKeyContent, "-----END EC PRIVATE KEY-----") === false) {
            $privateKeyContent = "-----BEGIN EC PRIVATE KEY-----\n" .
                chunk_split($privateKeyContent, 64, "\n") .
                "-----END EC PRIVATE KEY-----\n";
        }

        $privateKey = openssl_pkey_get_private($privateKeyContent);

        if ($privateKey === false) {
            throw new InvoiceSigningException('Failed to read private key.');
        }

        $signature = '';

        if (!openssl_sign($hashBytes, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new InvoiceSigningException('Failed to sign the data.');
        }

        return base64_encode($signature);
    }
}
