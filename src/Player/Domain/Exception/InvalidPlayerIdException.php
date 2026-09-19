<?php

namespace App\Player\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPlayerIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Player ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Player ID format provided "'.$value.'"');
    }
}
