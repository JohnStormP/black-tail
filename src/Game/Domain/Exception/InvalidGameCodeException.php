<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidGameCodeException extends DomainException
{
    public static function empty(): self
    {
        return new self('Game code cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Game code format provided "'.$value.'"');
    }
}
