<?php

namespace App\Game\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssignGameToTenantRequest
{
    private const string ULID = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 26)]
        #[Assert\Regex(pattern: self::ULID)]
        public string $tenantGameId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 26)]
        #[Assert\Regex(pattern: self::ULID)]
        public string $tenantId,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 26)]
        #[Assert\Regex(pattern: self::ULID)]
        public string $gameId,
    ) {
    }
}
