<?php

namespace App\Bonus\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPlayerBonusIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Player bonus ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Player bonus ID format provided "'.$value.'"');
    }
}
