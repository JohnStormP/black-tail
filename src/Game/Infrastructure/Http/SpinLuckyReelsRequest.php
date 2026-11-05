<?php

namespace App\Game\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SpinLuckyReelsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $roundId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $gameId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $playerId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $walletId,
        #[Assert\GreaterThanOrEqual(10)]
        #[Assert\LessThanOrEqual(10_000)]
        public int $betMinor,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        #[Assert\Choice(choices: HttpPayloadLimits::CURRENCIES)]
        public string $currency,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $reserveEntryId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $settleEntryId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $payoutEntryId,
    ) {
    }
}
