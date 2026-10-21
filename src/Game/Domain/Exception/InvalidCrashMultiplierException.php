<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidCrashMultiplierException extends DomainException
{
    public static function belowMinimum(int $hundredths): self
    {
        return new self('Crash multiplier cannot be below 1.00x, got hundredths '.$hundredths);
    }

    public static function aboveMaximum(int $hundredths, int $maxHundredths): self
    {
        return new self(
            'Crash multiplier hundredths '.$hundredths.' exceeds maximum '.$maxHundredths
        );
    }

    public static function nonPositiveBet(int $betMinor): self
    {
        return new self('Crash payout bet must be positive, got '.$betMinor);
    }
}
