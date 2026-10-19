<?php

namespace App\Risk\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidRiskRuleException extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Risk rule name cannot be empty');
    }

    public static function nonPositiveThreshold(): self
    {
        return new self('Risk rule threshold must be greater than zero');
    }

    public static function nonPositiveWindowSeconds(): self
    {
        return new self('Risk rule window seconds must be greater than zero when provided');
    }

    public static function windowRequiredForVelocity(): self
    {
        return new self('Bet velocity risk rule requires window seconds');
    }
}
