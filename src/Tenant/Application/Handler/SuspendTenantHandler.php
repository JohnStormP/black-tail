<?php

namespace App\Tenant\Application\Handler;

use App\Tenant\Application\Command\SuspendTenantCommand;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;

final class SuspendTenantHandler
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function __invoke(SuspendTenantCommand $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $tenant = $this->tenants->getById($tenantId);
        $tenant->suspend();
        $this->tenants->save($tenant);
    }
}
