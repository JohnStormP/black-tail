<?php

namespace App\Tenant\Application\Command;

final readonly class RegisterTenantCommand
{
    public function __construct(
        public string $tenantId,
        public string $name,
    ) {
    }
}
