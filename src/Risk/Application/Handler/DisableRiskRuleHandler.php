<?php

namespace App\Risk\Application\Handler;

use App\Risk\Application\Command\DisableRiskRuleCommand;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleId;

final class DisableRiskRuleHandler
{
    public function __construct(
        private readonly RiskRuleRepositoryInterface $rules,
    ) {
    }

    public function __invoke(DisableRiskRuleCommand $command): void
    {
        $id = RiskRuleId::fromString($command->ruleId);
        $rule = $this->rules->getById($id);
        $rule->disable();
        $this->rules->save($rule);
    }
}
