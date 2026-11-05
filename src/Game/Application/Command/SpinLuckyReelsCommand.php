<?php

namespace App\Game\Application\Command;

final readonly class SpinLuckyReelsCommand
{
    public function __construct(
        public string $roundId,
        public string $gameId,
        public string $playerId,
        public string $walletId,
        public int $betMinor,
        public string $currency,
        public string $reserveEntryId,
        public string $settleEntryId,
        public string $payoutEntryId,
    ) {
    }
}
