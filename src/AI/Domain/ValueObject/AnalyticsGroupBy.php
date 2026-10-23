<?php

namespace App\AI\Domain\ValueObject;

enum AnalyticsGroupBy: string
{
    case Day = 'DAY';
    case Game = 'GAME';
    case Country = 'COUNTRY';
}
