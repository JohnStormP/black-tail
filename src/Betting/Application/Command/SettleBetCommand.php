<?php

namespace App\Betting\Application\Command;

final readonly class SettleBetCommand
{
    public function __construct(
        public string $betId,
        public string $walletId,
        public string $settleEntryId,
        public string $outcome,
        public int $payoutMinor = 0,
        public ?string $payoutEntryId = null,
    ) {
    }
}
