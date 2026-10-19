<?php

namespace App\Risk\Domain\ValueObject;

enum RiskRuleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
}
