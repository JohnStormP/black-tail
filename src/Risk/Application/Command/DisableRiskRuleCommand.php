<?php

namespace App\Risk\Application\Command;

final readonly class DisableRiskRuleCommand
{
    public function __construct(
        public string $ruleId,
    ) {
    }
}
