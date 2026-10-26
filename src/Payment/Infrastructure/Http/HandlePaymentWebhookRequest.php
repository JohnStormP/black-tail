<?php

namespace App\Payment\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentWebhookRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $providerReference,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['completed', 'failed'])]
        public string $outcome,
        #[Assert\NotBlank]
        public string $ledgerEntryId,
        public ?string $releaseEntryId = null,
        public ?string $failureReason = null,
    ) {
    }
}
