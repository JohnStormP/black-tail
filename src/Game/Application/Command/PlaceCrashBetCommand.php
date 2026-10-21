<?php

namespace App\Game\Application\Command;

final readonly class PlaceCrashBetCommand
{
    public function __construct(
        public string $betId,
        public string $roundId,
        public string $playerId,
        public string $walletId,
        public string $reserveEntryId,
        public int $amountMinor,
        public string $currency,
    ) {
    }
}
