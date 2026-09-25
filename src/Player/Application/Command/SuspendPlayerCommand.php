<?php

namespace App\Player\Application\Command;

final readonly class SuspendPlayerCommand
{
    public function __construct(
        public string $playerId,
    ) {
    }
}
