<?php

namespace App\Bonus\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidBonusCampaignException extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Bonus campaign name cannot be empty');
    }

    public static function nonPositiveBonusAmount(): self
    {
        return new self('Bonus campaign amount must be greater than zero');
    }

    public static function invalidWageringMultiplier(int $multiplier): self
    {
        return new self('Bonus campaign wagering multiplier must be at least 1, got "'.$multiplier.'"');
    }
}
