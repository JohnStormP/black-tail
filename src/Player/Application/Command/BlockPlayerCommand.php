<?php

namespace App\Player\Application\Command;

final readonly class BlockPlayerCommand
{
    public function __construct(
        public string $playerId,
    ) {
    }
}
