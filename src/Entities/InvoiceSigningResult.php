<?php

declare(strict_types=1);

namespace Smart\Zatca\Entities;

class InvoiceSigningResult
{
    public function __construct(
        public string $signature,
        public string $b64SignedInvoice,
        public string $b64QrCode,
    ) {
    }
}
