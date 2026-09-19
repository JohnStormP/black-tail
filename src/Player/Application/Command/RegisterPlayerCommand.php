<?php

namespace App\Player\Application\Command;

final readonly class RegisterPlayerCommand
{
    public function __construct(
        public string $playerId,
        public string $tenantId,
        public string $email,
    ) {
    }
}
