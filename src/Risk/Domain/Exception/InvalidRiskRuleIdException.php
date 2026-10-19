<?php

namespace App\Risk\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidRiskRuleIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Risk rule ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Risk rule ID format provided "'.$value.'"');
    }
}
