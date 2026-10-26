<?php

namespace App\Betting\Application\Query;

final readonly class GetBetQuery
{
    public function __construct(
        public string $betId,
    ) {
    }
}
