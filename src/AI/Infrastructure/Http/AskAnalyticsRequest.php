<?php

namespace App\AI\Infrastructure\Http;

use App\AI\Application\Ask\AskAnalyticsLimits;
use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AskAnalyticsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: AskAnalyticsLimits::MAX_QUESTION_LENGTH)]
        public string $question,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $tenantId,
    ) {
    }
}
