<?php

namespace App\Tenant\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidTenantNameException extends DomainException
{
    public static function empty(): self
    {
        return new self('Tenant Name cannot be empty');
    }
}
