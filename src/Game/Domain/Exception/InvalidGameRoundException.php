<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidGameRoundException extends DomainException
{
    public static function betNotAllowed(int $betMinor): self
    {
        return new self('Bet amount '.$betMinor.' is not allowed for this game');
    }
}
