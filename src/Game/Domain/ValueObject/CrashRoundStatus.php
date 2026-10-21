<?php

namespace App\Game\Domain\ValueObject;

enum CrashRoundStatus: string
{
    case Waiting = 'waiting';
    case Running = 'running';
    case Crashed = 'crashed';
}
