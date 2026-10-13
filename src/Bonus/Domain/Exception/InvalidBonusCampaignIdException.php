<?php

namespace App\Bonus\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidBonusCampaignIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Bonus campaign ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Bonus campaign ID format provided "'.$value.'"');
    }
}
