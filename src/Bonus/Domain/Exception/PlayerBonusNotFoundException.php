<?php

namespace App\Bonus\Domain\Exception;

use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Shared\Domain\Exception\DomainException;

final class PlayerBonusNotFoundException extends DomainException
{
    public static function withId(PlayerBonusId $id): self
    {
        return new self('Player bonus with id "'.$id->toString().'" cannot be found');
    }
}
