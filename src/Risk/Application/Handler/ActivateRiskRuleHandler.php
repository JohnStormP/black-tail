<?php

namespace App\Risk\Application\Handler;

use App\Risk\Application\Command\ActivateRiskRuleCommand;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleId;

final class ActivateRiskRuleHandler
{
    public function __construct(
        private readonly RiskRuleRepositoryInterface $rules,
    ) {
    }

    public function __invoke(ActivateRiskRuleCommand $command): void
    {
        $id = RiskRuleId::fromString($command->ruleId);
        $rule = $this->rules->getById($id);
        $rule->activate();
        $this->rules->save($rule);
    }
}
