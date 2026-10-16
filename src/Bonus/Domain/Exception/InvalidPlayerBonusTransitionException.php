<?php

namespace App\Bonus\Domain\Exception;

use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidPlayerBonusTransitionException extends DomainException
{
    public static function cannotTransition(PlayerBonusStatus $from, PlayerBonusStatus $to): self
    {
        return new self('Cannot transition player bonus from "'.$from->value.'" to "'.$to->value.'"');
    }

    public static function cannotApplyWagering(PlayerBonusStatus $status): self
    {
        return new self('Cannot apply wagering to player bonus in status "'.$status->value.'"');
    }
}
