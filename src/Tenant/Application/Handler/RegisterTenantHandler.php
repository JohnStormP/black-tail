<?php

namespace App\Tenant\Application\Handler;

use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;

final class RegisterTenantHandler
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function __invoke(RegisterTenantCommand $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $tenant = Tenant::register($tenantId, $command->name);
        $this->tenants->save($tenant);
    }
}
