<?php

namespace App\Identity\Domain\ValueObject;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
