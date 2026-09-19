<?php

namespace App\Tenant\Application\Command;

final readonly class SuspendTenantCommand
{
    public function __construct(
        public string $tenantId,
    ) {
    }
}
