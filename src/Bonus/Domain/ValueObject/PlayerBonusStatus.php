<?php

namespace App\Bonus\Domain\ValueObject;

enum PlayerBonusStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
