<?php

namespace App\Analytics\Application\Query;

final readonly class GetGgrByDayQuery
{
    /**
     * @param non-empty-string $fromDate inclusive day in Y-m-d (UTC)
     * @param non-empty-string $toDate   inclusive day in Y-m-d (UTC)
     */
    public function __construct(
        public string $fromDate,
        public string $toDate,
    ) {
    }
}
