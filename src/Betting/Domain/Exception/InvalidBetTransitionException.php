<?php

namespace App\Betting\Domain\Exception;

use App\Betting\Domain\ValueObject\BetStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidBetTransitionException extends DomainException
{
    public static function cannotTransition(BetStatus $from, BetStatus $to): self
    {
        return new self('Cannot transition bet from "'.$from->value.'" to "'.$to->value.'"');
    }
}
