<?php

namespace App\Risk\Application\Command;

final readonly class CreateRiskRuleCommand
{
    public function __construct(
        public string $ruleId,
        public string $tenantId,
        public string $name,
        public string $type,
        public int $threshold,
        public string $action,
        public ?int $windowSeconds = null,
    ) {
    }
}
