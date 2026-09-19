<?php

namespace App\Tenant\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidTenantIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Tenant ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Tenant ID format provided "'.$value.'"');
    }
}
