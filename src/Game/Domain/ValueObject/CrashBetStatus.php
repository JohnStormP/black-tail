<?php

namespace App\Game\Domain\ValueObject;

enum CrashBetStatus: string
{
    case Open = 'open';
    case CashedOut = 'cashed_out';
    case Lost = 'lost';
}
