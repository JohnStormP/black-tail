<?php

namespace App\AI\Application\Ask;

use App\AI\Domain\Exception\UnsupportedAnalyticsIntentException;
use App\AI\Domain\ValueObject\AnalyticsGroupBy;
use App\AI\Domain\ValueObject\AnalyticsIntent;
use App\AI\Domain\ValueObject\AnalyticsMetric;

final class AnalyticsIntentAllowlist
{
    public function assertSupported(AnalyticsIntent $intent): void
    {
        if (AnalyticsMetric::Bets !== $intent->metric || AnalyticsGroupBy::Day !== $intent->groupBy) {
            throw UnsupportedAnalyticsIntentException::combination($intent->metric, $intent->groupBy, $intent->period);
        }
    }
}
