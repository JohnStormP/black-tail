<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidCrashBetTransitionException extends DomainException
{
    public static function cannotTransition(CrashBetStatus $from, CrashBetStatus $to): self
    {
        return new self(
            'Cannot transition crash bet from "'.$from->value.'" to "'.$to->value.'"'
        );
    }
}
