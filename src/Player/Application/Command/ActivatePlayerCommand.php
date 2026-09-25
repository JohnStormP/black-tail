<?php

namespace App\Player\Application\Command;

final readonly class ActivatePlayerCommand
{
    public function __construct(
        public string $playerId,
    ) {
    }
}
