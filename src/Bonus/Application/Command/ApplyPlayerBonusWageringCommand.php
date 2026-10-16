<?php

namespace App\Bonus\Application\Command;

final readonly class ApplyPlayerBonusWageringCommand
{
    public function __construct(
        public string $playerBonusId,
        public int $amountMinor,
    ) {
    }
}
