<?php

namespace App\AI\Domain\ValueObject;

enum AnalyticsPeriod: string
{
    case Today = 'TODAY';
    case LastWeek = 'LAST_WEEK';
    case LastMonth = 'LAST_MONTH';
}
