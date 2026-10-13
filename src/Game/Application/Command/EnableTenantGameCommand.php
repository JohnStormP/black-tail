<?php

namespace App\Game\Application\Command;

final readonly class EnableTenantGameCommand
{
    public function __construct(
        public string $tenantGameId,
    ) {
    }
}
