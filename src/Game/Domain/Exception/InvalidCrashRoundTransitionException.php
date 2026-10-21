<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\CrashRoundStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidCrashRoundTransitionException extends DomainException
{
    public static function cannotTransition(CrashRoundStatus $from, CrashRoundStatus $to): self
    {
        return new self(
            'Cannot transition crash round from "'.$from->value.'" to "'.$to->value.'"'
        );
    }
}
