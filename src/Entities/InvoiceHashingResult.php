<?php

declare(strict_types=1);

namespace Smart\Zatca\Entities;

class InvoiceHashingResult
{
    public function __construct(
        public string $invoiceHash,
        public string $uuid,
        public string $b64Invoice,
        public string $b64CanonicalXml,
    ) {
    }
}
