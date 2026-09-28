<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameCode;
use App\Shared\Domain\Exception\DomainException;

final class GameProviderNotFoundException extends DomainException
{
    public static function forCode(GameCode $code): self
    {
        return new self('No game provider registered for code "'.$code->toString().'"');
    }
}
