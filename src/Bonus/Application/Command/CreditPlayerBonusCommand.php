<?php

namespace App\Bonus\Application\Command;

final readonly class CreditPlayerBonusCommand
{
    public function __construct(
        public string $playerBonusId,
        public string $walletId,
        public string $creditEntryId,
    ) {
    }
}
