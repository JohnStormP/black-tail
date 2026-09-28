<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameId;
use App\Shared\Domain\Exception\DomainException;

final class GameNotPlayableException extends DomainException
{
    public static function disabled(GameId $id): self
    {
        return new self('Game with id "'.$id->toString().'" is disabled and cannot be played');
    }
}
