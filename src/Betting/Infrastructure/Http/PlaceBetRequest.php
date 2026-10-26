<?php

namespace App\Betting\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlaceBetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $betId,
        #[Assert\NotBlank]
        public string $playerId,
        #[Assert\NotBlank]
        public string $gameId,
        #[Assert\NotBlank]
        public string $roundId,
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        public string $currency,
        #[Assert\NotBlank]
        public string $idempotencyKey,
        public ?string $providerReference = null,
    ) {
    }
}
