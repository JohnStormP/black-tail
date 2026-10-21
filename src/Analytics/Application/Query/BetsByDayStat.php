<?php

namespace App\Analytics\Application\Query;

final readonly class BetsByDayStat
{
    public function __construct(
        public string $day,
        public int $betsCount,
        public int $amountMinor,
    ) {
    }
}
