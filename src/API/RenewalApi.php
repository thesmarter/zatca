<?php

declare(strict_types=1);

namespace Zid\Zatca\API;

class RenewalApi implements ZatcaApiInterface
{
    public function __construct(protected ZatcaClient $client)
    {
    }

    /**
     * Renew an existing production CSID with a fresh OTP and CSR.
     *
     * Maps to PATCH /production/csids on every environment tier.
     */
    public function renewProductionCertificate(string $binarySecurityToken, string $secret, string $otp, string $b64Csr, string $language = 'en'): array
    {
        return $this->client->patchRequest('production/csids', [
            'otp' => $otp,
            'csr' => $b64Csr,
        ], [
            'Authorization' => 'Basic ' . base64_encode(
                "$binarySecurityToken:$secret"
            ),
            'Accept-Language' => $language,
            'Content-Type' => 'application/json',
        ]);
    }
}
