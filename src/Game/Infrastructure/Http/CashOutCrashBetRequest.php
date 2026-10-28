<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CashOutCrashBetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $settleEntryId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $payoutEntryId,
        #[Assert\GreaterThanOrEqual(CrashMultiplier::MIN_HUNDREDTHS)]
        #[Assert\LessThanOrEqual(CrashMultiplier::MAX_HUNDREDTHS)]
        public int $cashOutHundredths,
    ) {
    }
}
