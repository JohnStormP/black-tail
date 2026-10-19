<?php

namespace App\Tests\Risk\Doubles;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\RiskRuleNotFoundException;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Tenant\Domain\ValueObject\TenantId;

final class InMemoryRiskRuleRepository implements RiskRuleRepositoryInterface
{
    /** @var array<string, RiskRule> */
    private array $rules = [];

    public function save(RiskRule $rule): void
    {
        $this->rules[$rule->id()->toString()] = $rule;
    }

    public function findById(RiskRuleId $id): ?RiskRule
    {
        return $this->rules[$id->toString()] ?? null;
    }

    public function getById(RiskRuleId $id): RiskRule
    {
        $rule = $this->findById($id);
        if (null === $rule) {
            throw RiskRuleNotFoundException::withId($id);
        }

        return $rule;
    }

    public function findActiveByTenant(TenantId $tenantId): array
    {
        $active = [];
        foreach ($this->rules as $rule) {
            if ($rule->tenantId()->equals($tenantId) && $rule->isActive()) {
                $active[] = $rule;
            }
        }

        return $active;
    }
}
