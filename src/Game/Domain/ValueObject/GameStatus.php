<?php

namespace App\Game\Domain\ValueObject;

enum GameStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
