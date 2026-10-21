<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\CrashBetId;
use App\Shared\Domain\Exception\DomainException;

final class CrashBetNotFoundException extends DomainException
{
    public static function withId(CrashBetId $id): self
    {
        return new self('Crash bet "'.$id->toString().'" cannot be found');
    }
}
