<?php

namespace App\Game\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterGameRequest
{
    private const string ULID = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';
    private const string GAME_CODE = '/^[a-z][a-z0-9_]{1,63}$/i';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 26)]
        #[Assert\Regex(pattern: self::ULID)]
        public string $gameId,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 64)]
        #[Assert\Regex(pattern: self::GAME_CODE)]
        public string $code,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
    ) {
    }
}
