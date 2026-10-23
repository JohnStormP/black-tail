<?php

namespace App\AI\Domain\ValueObject;

enum AnalyticsMetric: string
{
    case Ggr = 'GGR';
    case Bets = 'BETS';
    case Deposits = 'DEPOSITS';
}
