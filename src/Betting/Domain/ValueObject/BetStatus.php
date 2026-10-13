<?php

namespace App\Betting\Domain\ValueObject;

enum BetStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
