<?php

namespace App\Betting\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SettleBetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $walletId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $settleEntryId,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['won', 'lost'])]
        public string $outcome,
        #[Assert\GreaterThanOrEqual(0)]
        #[Assert\LessThanOrEqual(HttpPayloadLimits::MAX_AMOUNT_MINOR)]
        public int $payoutMinor = 0,
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public ?string $payoutEntryId = null,
    ) {
    }
}
