<?php

namespace App\Betting\Application\Message;

final readonly class LogBetPlacedMessage
{
    public function __construct(
        public string $betId,
        public string $playerId,
        public int $amountMinor,
        public string $currency,
    ) {
    }
}
