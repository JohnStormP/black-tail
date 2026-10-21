<?php

namespace App\Game\Application\Command;

final readonly class OpenCrashRoundCommand
{
    public function __construct(
        public string $roundId,
    ) {
    }
}
