<?php

require_once("../vendor/autoload.php");

use Smart\Zatca\Enums\ZatcaEnvironment;

$productionCsidGenerator = new \Smart\Zatca\ProductionCsidGeneratorService(ZatcaEnvironment::SANDBOX);

// A fresh OTP from the Fatoora portal is required for every renewal,
// plus a newly generated CSR (see 1_generate_csr.php).
$otp = $argv[1] ?? null;
$csrFile = $argv[2] ?? 'output/csr.txt';

if ($otp === null) {
    echo "Usage: php 9_renew_production_csid.php <otp> [csr-file]\n";
    exit(1);
}

$pcsid = \Smart\Zatca\Entities\CSID::loadFromJson('output/pcsid.json');

$renewed = $productionCsidGenerator->renewProductionCertificate(
    binarySecurityToken: $pcsid->certificate,
    secret: $pcsid->secret,
    otp: $otp,
    b64Csr: base64_encode(trim(file_get_contents($csrFile))),
);

echo 'Renewed Production CSID:' . PHP_EOL;
echo $renewed->certificate . PHP_EOL;
echo 'Secret:' . PHP_EOL;
echo $renewed->secret . PHP_EOL;
echo 'Request ID:' . PHP_EOL;
echo $renewed->requestId . PHP_EOL;

$renewed->saveAsJson('output/pcsid.json');
