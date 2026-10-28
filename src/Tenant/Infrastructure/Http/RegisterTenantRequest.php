<?php

namespace App\Tenant\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterTenantRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: HttpPayloadLimits::ULID_LENGTH)]
        #[Assert\Regex(pattern: HttpPayloadLimits::ULID)]
        public string $tenantId,
        #[Assert\NotBlank]
        #[Assert\Length(max: HttpPayloadLimits::NAME_MAX)]
        public string $name,
    ) {
    }
}
