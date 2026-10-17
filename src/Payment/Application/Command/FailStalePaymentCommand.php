<?php

namespace App\Payment\Application\Command;

final readonly class FailStalePaymentCommand
{
    public function __construct(
        public string $paymentId,
        public string $staleBefore,
        public ?string $releaseEntryId = null,
        public string $reason = 'stale_timeout',
    ) {
    }
}
