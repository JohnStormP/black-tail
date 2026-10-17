<?php

namespace App\Payment\Application\Command;

final readonly class HandlePaymentWebhookCommand
{
    public function __construct(
        public string $provider,
        public string $providerReference,
        public string $outcome,
        public string $ledgerEntryId,
        public ?string $releaseEntryId = null,
        public ?string $failureReason = null,
    ) {
    }
}
