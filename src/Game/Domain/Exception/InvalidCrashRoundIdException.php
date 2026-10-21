<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidCrashRoundIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Crash Round ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Crash Round ID format provided "'.$value.'"');
    }
}
