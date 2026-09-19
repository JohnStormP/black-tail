<?php

namespace App\Tenant\Application\Handler;

use App\Tenant\Application\Command\ActivateTenantCommand;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;

final class ActivateTenantHandler
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function __invoke(ActivateTenantCommand $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $tenant = $this->tenants->getById($tenantId);
        $tenant->activate();
        $this->tenants->save($tenant);
    }
}
