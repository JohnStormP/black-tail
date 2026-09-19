<?php

namespace App\Player\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPlayerEmailException extends DomainException
{
    public static function empty(): self
    {
        return new self('Player email cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Player email format provided "'.$value.'"');
    }
}
