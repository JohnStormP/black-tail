<?php

namespace App\Risk\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRiskRuleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $ruleId,
        #[Assert\NotBlank]
        public string $tenantId,
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $type,
        #[Assert\Positive]
        public int $threshold,
        #[Assert\NotBlank]
        public string $action,
        #[Assert\Positive]
        public ?int $windowSeconds = null,
    ) {
    }
}
