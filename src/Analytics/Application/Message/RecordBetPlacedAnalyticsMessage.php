<?php

namespace App\Analytics\Application\Message;

final readonly class RecordBetPlacedAnalyticsMessage
{
    public function __construct(
        public string $betId,
        public string $playerId,
        public string $gameId,
        public string $roundId,
        public int $amountMinor,
        public string $currency,
        public string $occurredAt,
        public string $tenantId,
    ) {
    }
}
