<?php

namespace App\Game\Domain\ValueObject;

final readonly class LuckyReelsLineWin
{
    public function __construct(
        public int $paylineIndex,
        public ReelSymbol $symbol,
        public int $matchCount,
        public int $multiplier,
    ) {
    }
}
