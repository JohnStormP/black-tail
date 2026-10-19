<?php

namespace App\Risk\Domain\ValueObject;

enum RiskRuleAction: string
{
    case Block = 'block';
    case Flag = 'flag';
    case ManualReview = 'manual_review';
}
