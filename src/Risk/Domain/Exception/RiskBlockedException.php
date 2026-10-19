<?php

namespace App\Risk\Domain\Exception;

use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Shared\Domain\Exception\DomainException;

final class RiskBlockedException extends DomainException
{
    public static function byRule(RiskRuleId $ruleId, string $ruleName): self
    {
        return new self('Operation blocked by risk rule "'.$ruleName.'" ('.$ruleId->toString().')');
    }
}
