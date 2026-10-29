<?php

namespace App\Analytics\Application\Message;

final readonly class RecordBetSettledAnalyticsMessage
{
    public function __construct(
        public string $betId,
        public string $playerId,
        public string $outcome,
        public int $stakeMinor,
        public int $payoutMinor,
        public string $currency,
        public string $occurredAt,
    ) {
    }
}
