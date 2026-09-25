<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidGameIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Game ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Game ID format provided "'.$value.'"');
    }
}
