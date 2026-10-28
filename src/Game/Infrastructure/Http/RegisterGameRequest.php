<?php

namespace App\Game\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterGameRequest
{
    private const string GAME_CODE = '/^[a-z][a-z0-9_]{1,63}$/i';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $gameId,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 64)]
        #[Assert\Regex(pattern: self::GAME_CODE)]
        public string $code,
        #[Assert\NotBlank]
        #[Assert\Length(max: HttpPayloadLimits::NAME_MAX)]
        public string $name,
    ) {
    }
}
