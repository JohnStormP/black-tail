<?php

namespace App\Betting\Domain\Exception;

use App\Betting\Domain\ValueObject\BetId;
use App\Shared\Domain\Exception\DomainException;

final class BetNotFoundException extends DomainException
{
    public static function withId(BetId $id): self
    {
        return new self('Bet with id "'.$id->toString().'" cannot be found');
    }
}
