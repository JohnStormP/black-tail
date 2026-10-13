<?php

namespace App\Betting\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidBetException extends DomainException
{
    public static function nonPositiveAmount(): self
    {
        return new self('Bet amount must be greater than zero');
    }
}
