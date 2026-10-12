<?php

namespace App\Game\Application\Command;

final readonly class AssignGameToTenantCommand
{
    public function __construct(
        public string $tenantGameId,
        public string $tenantId,
        public string $gameId,
    ) {
    }
}
