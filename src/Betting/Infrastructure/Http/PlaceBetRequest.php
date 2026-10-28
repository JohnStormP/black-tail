<?php

namespace App\Betting\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlaceBetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $betId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $playerId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $gameId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $roundId,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(HttpPayloadLimits::MAX_AMOUNT_MINOR)]
        public int $amountMinor,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        #[Assert\Choice(choices: HttpPayloadLimits::CURRENCIES)]
        public string $currency,
        #[Assert\NotBlank]
        #[Assert\Length(max: HttpPayloadLimits::IDEMPOTENCY_KEY_MAX)]
        public string $idempotencyKey,
        #[Assert\Length(max: HttpPayloadLimits::PROVIDER_REFERENCE_MAX)]
        public ?string $providerReference = null,
    ) {
    }
}
