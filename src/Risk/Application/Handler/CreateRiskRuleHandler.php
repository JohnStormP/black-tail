<?php

namespace App\Risk\Application\Handler;

use App\Risk\Application\Command\CreateRiskRuleCommand;
use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;

final class CreateRiskRuleHandler
{
    public function __construct(
        private readonly RiskRuleRepositoryInterface $rules,
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function __invoke(CreateRiskRuleCommand $command): void
    {
        $id = RiskRuleId::fromString($command->ruleId);
        $tenantId = TenantId::fromString($command->tenantId);
        $this->tenants->getById($tenantId);
        $type = RiskRuleType::from($command->type);
        $action = RiskRuleAction::from($command->action);
        $rule = RiskRule::create($id, $tenantId, $command->name, $type, $command->threshold, $action, $command->windowSeconds);
        $this->rules->save($rule);
    }
}
