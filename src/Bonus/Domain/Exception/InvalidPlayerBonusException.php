<?php

namespace App\Bonus\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPlayerBonusException extends DomainException
{
    public static function nonPositiveGrantedAmount(): self
    {
        return new self('Player bonus granted amount must be greater than zero');
    }

    public static function nonPositiveWageringRequired(): self
    {
        return new self('Player bonus wagering required must be greater than zero');
    }

    public static function currencyMismatch(): self
    {
        return new self('Player bonus granted amount and wagering required must use the same currency');
    }

    public static function nonPositiveWageringAmount(): self
    {
        return new self('Wagering amount must be greater than zero');
    }
}
