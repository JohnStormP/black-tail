<?php

namespace App\Payment\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentWebhookRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: HttpPayloadLimits::PROVIDER_REFERENCE_MAX)]
        public string $providerReference,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['completed', 'failed'])]
        public string $outcome,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $ledgerEntryId,
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public ?string $releaseEntryId = null,
        #[Assert\Length(max: HttpPayloadLimits::FAILURE_REASON_MAX)]
        public ?string $failureReason = null,
    ) {
    }
}
