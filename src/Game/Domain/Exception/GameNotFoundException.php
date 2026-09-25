<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameId;
use App\Shared\Domain\Exception\DomainException;

final class GameNotFoundException extends DomainException
{
    public static function withId(GameId $id): self
    {
        return new self('Game with id "'.$id->toString().'" cannot be found');
    }
}
