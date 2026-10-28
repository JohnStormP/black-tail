<?php

namespace App\Payment\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class InitiateDepositRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $paymentId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $playerId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $walletId,
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
        #[Assert\NotBlank]
        #[Assert\Length(max: HttpPayloadLimits::PROVIDER_MAX)]
        public string $provider = 'fake',
    ) {
    }
}
