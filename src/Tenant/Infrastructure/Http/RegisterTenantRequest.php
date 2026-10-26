<?php

namespace App\Tenant\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterTenantRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $tenantId,
        #[Assert\NotBlank]
        public string $name,
    ) {
    }
}
