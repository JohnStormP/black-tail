<?php

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidUserIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('User ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid User ID format provided "'.$value.'"');
    }
}
