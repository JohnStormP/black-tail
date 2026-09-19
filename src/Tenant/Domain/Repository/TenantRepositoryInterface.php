<?php

namespace App\Tenant\Domain\Repository;

use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\ValueObject\TenantId;

interface TenantRepositoryInterface
{
    public function save(Tenant $tenant): void;

    public function findById(TenantId $id): ?Tenant;

    /**
     * @throws TenantNotFoundException
     */
    public function getById(TenantId $id): Tenant;
}
