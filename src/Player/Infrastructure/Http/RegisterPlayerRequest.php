<?php

namespace App\Player\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterPlayerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $playerId,
        #[Assert\NotBlank]
        public string $tenantId,
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}
