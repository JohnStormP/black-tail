<?php

namespace App\Betting\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidBetIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Bet ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Bet ID format provided "'.$value.'"');
    }
}
