<?php

namespace App\Betting\Application\Command;

final readonly class PlaceBetCommand
{
    public function __construct(
        public string $betId,
        public string $playerId,
        public string $gameId,
        public string $roundId,
        public int $amountMinor,
        public string $currency,
        public string $idempotencyKey,
        public ?string $providerReference = null,
    ) {
    }
}
