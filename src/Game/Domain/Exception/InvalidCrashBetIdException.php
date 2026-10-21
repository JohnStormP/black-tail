<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidCrashBetIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Crash Bet ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Crash Bet ID format provided "'.$value.'"');
    }
}
