<?php

namespace App\Risk\Application\Command;

final readonly class ActivateRiskRuleCommand
{
    public function __construct(
        public string $ruleId,
    ) {
    }
}
