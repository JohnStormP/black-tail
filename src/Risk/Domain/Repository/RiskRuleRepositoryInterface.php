<?php

namespace App\Risk\Domain\Repository;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\RiskRuleNotFoundException;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Tenant\Domain\ValueObject\TenantId;

interface RiskRuleRepositoryInterface
{
    public function save(RiskRule $rule): void;

    public function findById(RiskRuleId $id): ?RiskRule;

    /**
     * @throws RiskRuleNotFoundException
     */
    public function getById(RiskRuleId $id): RiskRule;

    /**
     * Active rules for a tenant, oldest first (stable evaluation order).
     *
     * @return list<RiskRule>
     */
    public function findActiveByTenant(TenantId $tenantId): array;
}
