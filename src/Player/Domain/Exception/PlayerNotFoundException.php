<?php

namespace App\Player\Domain\Exception;

use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Exception\DomainException;

final class PlayerNotFoundException extends DomainException
{
    public static function withId(PlayerId $id): self
    {
        return new self('Player with id "'.$id->toString().'" cannot be found');
    }
}
