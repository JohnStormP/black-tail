<?php

namespace App\Player\Application\Command;

final readonly class SelfExcludePlayerCommand
{
    public function __construct(
        public string $playerId,
    ) {
    }
}
