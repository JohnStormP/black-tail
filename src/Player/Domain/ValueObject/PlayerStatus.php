<?php

namespace App\Player\Domain\ValueObject;

enum PlayerStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case SelfExcluded = 'self_excluded';
    case Suspended = 'suspended';
}
