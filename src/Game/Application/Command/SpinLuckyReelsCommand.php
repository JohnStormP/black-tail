<?php

namespace App\Game\Application\Command;

final readonly class SpinLuckyReelsCommand
{
    public function __construct(
        public string $roundId,
        public string $gameId,
        public string $playerId,
        public int $betMinor,
    ) {
    }
}
