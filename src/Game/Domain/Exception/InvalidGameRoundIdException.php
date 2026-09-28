<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidGameRoundIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Game Round ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Game Round ID format provided "'.$value.'"');
    }
}
