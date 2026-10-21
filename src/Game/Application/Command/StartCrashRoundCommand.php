<?php

namespace App\Game\Application\Command;

final readonly class StartCrashRoundCommand
{
    public function __construct(
        public string $roundId,
    ) {
    }
}
