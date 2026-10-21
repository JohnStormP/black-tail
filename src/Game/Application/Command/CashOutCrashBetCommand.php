<?php

namespace App\Game\Application\Command;

final readonly class CashOutCrashBetCommand
{
    /**
     * @param int $cashOutHundredths multiplier in hundredths (150 = 1.50x)
     */
    public function __construct(
        public string $betId,
        public string $settleEntryId,
        public string $payoutEntryId,
        public int $cashOutHundredths,
    ) {
    }
}
