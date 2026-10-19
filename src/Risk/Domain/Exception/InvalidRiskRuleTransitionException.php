<?php

namespace App\Risk\Domain\Exception;

use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidRiskRuleTransitionException extends DomainException
{
    public static function cannotTransition(RiskRuleStatus $from, RiskRuleStatus $to): self
    {
        return new self('Cannot transition risk rule from "'.$from->value.'" to "'.$to->value.'"');
    }
}
