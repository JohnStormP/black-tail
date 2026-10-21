<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\CrashRoundId;
use App\Shared\Domain\Exception\DomainException;

final class CrashRoundNotFoundException extends DomainException
{
    public static function withId(CrashRoundId $id): self
    {
        return new self('Crash round "'.$id->toString().'" cannot be found');
    }
}
