<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidTenantGameIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Tenant game ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Tenant game ID format provided "'.$value.'"');
    }
}
