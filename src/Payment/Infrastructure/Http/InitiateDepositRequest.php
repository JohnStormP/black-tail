<?php

namespace App\Payment\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InitiateDepositRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $paymentId,
        #[Assert\NotBlank]
        public string $playerId,
        #[Assert\NotBlank]
        public string $walletId,
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        public string $currency,
        #[Assert\NotBlank]
        public string $idempotencyKey,
        #[Assert\NotBlank]
        public string $provider = 'fake',
    ) {
    }
}
