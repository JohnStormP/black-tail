<?php

namespace App\Risk\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRiskRuleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $ruleId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $tenantId,
        #[Assert\NotBlank]
        #[Assert\Length(max: HttpPayloadLimits::NAME_MAX)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: HttpPayloadLimits::RISK_RULE_TYPES)]
        public string $type,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(HttpPayloadLimits::MAX_AMOUNT_MINOR)]
        public int $threshold,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: HttpPayloadLimits::RISK_RULE_ACTIONS)]
        public string $action,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(HttpPayloadLimits::WINDOW_SECONDS_MAX)]
        public ?int $windowSeconds = null,
    ) {
    }
}
