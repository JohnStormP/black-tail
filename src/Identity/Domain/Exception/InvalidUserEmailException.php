<?php

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidUserEmailException extends DomainException
{
    public static function empty(): self
    {
        return new self('User email cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid User email format provided "'.$value.'"');
    }
}
