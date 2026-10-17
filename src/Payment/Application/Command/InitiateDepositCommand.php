<?php

namespace App\Payment\Application\Command;

final readonly class InitiateDepositCommand
{
    public function __construct(
        public string $paymentId,
        public string $playerId,
        public string $walletId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public string $provider = 'fake',
    ) {
    }
}
