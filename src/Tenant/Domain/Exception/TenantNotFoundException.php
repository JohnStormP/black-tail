<?php

namespace App\Tenant\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Tenant\Domain\ValueObject\TenantId;

final class TenantNotFoundException extends DomainException
{
    public static function withId(TenantId $id): self
    {
        return new self('Tenant with id "'.$id->toString().'" cannot be found');
    }
}
