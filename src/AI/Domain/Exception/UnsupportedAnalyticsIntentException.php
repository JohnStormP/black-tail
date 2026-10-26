<?php

namespace App\AI\Domain\Exception;

use App\AI\Domain\ValueObject\AnalyticsGroupBy;
use App\AI\Domain\ValueObject\AnalyticsMetric;
use App\AI\Domain\ValueObject\AnalyticsPeriod;
use App\Shared\Domain\Exception\DomainException;

final class UnsupportedAnalyticsIntentException extends DomainException
{
    public static function combination(
        AnalyticsMetric $metric,
        AnalyticsGroupBy $groupBy,
        AnalyticsPeriod $period,
    ): self {
        return new self(sprintf(
            'Unsupported analytics intent metric="%s" group_by="%s" period="%s"',
            $metric->value,
            $groupBy->value,
            $period->value,
        ));
    }
}
