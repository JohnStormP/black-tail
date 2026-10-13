<?php

namespace App\Game\Application\Command;

final readonly class DisableTenantGameCommand
{
    public function __construct(
        public string $tenantGameId,
    ) {
    }
}
