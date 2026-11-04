<?php

namespace App\Game\Application\Command;

final readonly class CashOutCrashBetCommand
{
    public function __construct(
        public string $betId,
        public string $settleEntryId,
        public string $payoutEntryId,
    ) {
    }
}
