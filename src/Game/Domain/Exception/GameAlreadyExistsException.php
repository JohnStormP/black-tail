<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameCode;
use App\Shared\Domain\Exception\DomainException;

final class GameAlreadyExistsException extends DomainException
{
    public static function withCode(GameCode $code): self
    {
        return new self('Game with code "'.$code->toString().'" already exists');
    }
}
