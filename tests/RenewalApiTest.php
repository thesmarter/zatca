<?php

namespace Smart\Zatca\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Smart\Zatca\API\ZatcaClient;
use Smart\Zatca\Enums\ZatcaEnvironment;
use Smart\Zatca\ProductionCsidGeneratorService;

class RenewalApiTest extends TestCase
{
    public function testRenewProductionCertificate(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'binarySecurityToken' => 'Tk9XQ0VSVA==',
                'secret' => 'Tk9XU0VDUkVU',
                'requestID' => 9876543210,
            ])),
        ]);

        $transactions = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($transactions));

        $client = new ZatcaClient(ZatcaEnvironment::SANDBOX, new Client(['handler' => $stack]));
        $service = new ProductionCsidGeneratorService(ZatcaEnvironment::SANDBOX, $client);

        $csid = $service->renewProductionCertificate('T0tFTg==', 'T0tTMU5VUFR+PQ==', '123456', 'Q1NS');

        $this->assertSame('Tk9XQ0VSVA==', $csid->certificate);
        $this->assertSame('Tk9XU0VDUkVU', $csid->secret);
        $this->assertSame(9876543210, $csid->requestId);

        $this->assertCount(1, $transactions);
        $request = $transactions[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('production/csids', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('123456', $body['otp']);
        $this->assertSame('Q1NS', $body['csr']);
    }
}
