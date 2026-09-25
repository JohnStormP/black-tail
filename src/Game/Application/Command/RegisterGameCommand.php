<?php

namespace App\Game\Application\Command;

final readonly class RegisterGameCommand
{
    public function __construct(
        public string $gameId,
        public string $code,
        public string $name,
    ) {
    }
}
