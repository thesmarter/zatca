<?php

declare(strict_types=1);

namespace Smart\Zatca;

use Smart\Zatca\API\ZatcaClient;
use Smart\Zatca\Entities\CSID;
use Smart\Zatca\Enums\ZatcaEnvironment;

class ProductionCsidGeneratorService
{
    private ZatcaClient $zatcaClient;

    public function __construct(
        ZatcaEnvironment $environment = ZatcaEnvironment::SANDBOX,
        ?ZatcaClient $client = null
    ) {
        $this->zatcaClient = $client ?? new ZatcaClient($environment);
    }

    public function requestProductionCertificate(string $binarySecurityToken, string $secret, string $ccsidRequestId): CSID
    {
        $result = $this->zatcaClient->productionApi()->requestProductionCertificate($binarySecurityToken, $secret, $ccsidRequestId);

        return new CSID(
            certificate: $result['binarySecurityToken'],
            secret: $result['secret'],
            requestId: $result['requestID'],
        );
    }

    public function renewProductionCertificate(string $binarySecurityToken, string $secret, string $otp, string $b64Csr): CSID
    {
        $result = $this->zatcaClient->renewalApi()->renewProductionCertificate($binarySecurityToken, $secret, $otp, $b64Csr);

        return new CSID(
            certificate: $result['binarySecurityToken'],
            secret: $result['secret'],
            requestId: $result['requestID'],
        );
    }
}
