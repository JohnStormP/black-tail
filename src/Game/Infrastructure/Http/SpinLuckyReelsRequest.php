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
        #[Assert\GreaterThanOrEqual(10)]
        #[Assert\LessThanOrEqual(10_000)]
        public int $betMinor,
    ) {
    }
}
