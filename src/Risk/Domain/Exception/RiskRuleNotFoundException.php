<?php

namespace App\Risk\Domain\Exception;

use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Shared\Domain\Exception\DomainException;

final class RiskRuleNotFoundException extends DomainException
{
    public static function withId(RiskRuleId $id): self
    {
        return new self('Risk rule with id "'.$id->toString().'" cannot be found');
    }
}
