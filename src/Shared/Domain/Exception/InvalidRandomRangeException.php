<?php

namespace App\Shared\Domain\Exception;

final class InvalidRandomRangeException extends DomainException
{
    public static function minGreaterThanMax(int $min, int $max): self
    {
        return new self('Random range invalid: min='.$min.' is greater than max='.$max);
    }
}
