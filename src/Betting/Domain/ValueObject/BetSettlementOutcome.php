<?php

namespace App\Betting\Domain\ValueObject;

enum BetSettlementOutcome: string
{
    case Won = 'won';
    case Lost = 'lost';
}
