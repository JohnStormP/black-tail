<?php

namespace App\Bonus\Domain\ValueObject;

enum BonusCampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
}
