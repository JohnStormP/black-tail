<?php

namespace App\Analytics\Application\Query;

final readonly class GgrByDayStat
{
    public function __construct(
        public string $day,
        public int $stakeMinor,
        public int $payoutMinor,
        public int $ggrMinor,
    ) {
    }
}
