<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameRoundId;
use App\Shared\Domain\Exception\DomainException;

final class GameRoundNotFoundException extends DomainException
{
    public static function withId(GameRoundId $id): self
    {
        return new self('Game round with id "'.$id->toString().'" cannot be found');
    }
}
