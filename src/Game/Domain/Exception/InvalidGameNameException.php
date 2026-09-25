<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidGameNameException extends DomainException
{
    public static function empty(): self
    {
        return new self('Game name cannot be empty');
    }
}
