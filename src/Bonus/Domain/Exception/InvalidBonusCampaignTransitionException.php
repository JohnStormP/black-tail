<?php

namespace App\Bonus\Domain\Exception;

use App\Bonus\Domain\ValueObject\BonusCampaignStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidBonusCampaignTransitionException extends DomainException
{
    public static function cannotTransition(BonusCampaignStatus $from, BonusCampaignStatus $to): self
    {
        return new self('Cannot transition bonus campaign from "'.$from->value.'" to "'.$to->value.'"');
    }
}
