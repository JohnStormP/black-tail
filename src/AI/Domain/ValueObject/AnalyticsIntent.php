<?php

namespace App\AI\Domain\ValueObject;

use App\AI\Domain\Exception\InvalidAnalyticsIntentException;

final readonly class AnalyticsIntent
{
    public function __construct(
        public AnalyticsMetric $metric,
        public AnalyticsGroupBy $groupBy,
        public AnalyticsPeriod $period,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $metric = self::requireNonEmptyString($data, 'metric');
        $groupBy = self::requireNonEmptyString($data, 'group_by');
        $period = self::requireNonEmptyString($data, 'period');

        $analyticsMetric = AnalyticsMetric::tryFrom($metric);

        if (null === $analyticsMetric) {
            throw InvalidAnalyticsIntentException::invalidMetric($metric);
        }

        $analyticsGroupBy = AnalyticsGroupBy::tryFrom($groupBy);

        if (null === $analyticsGroupBy) {
            throw InvalidAnalyticsIntentException::invalidGroupBy($groupBy);
        }

        $analyticsPeriod = AnalyticsPeriod::tryFrom($period);

        if (null === $analyticsPeriod) {
            throw InvalidAnalyticsIntentException::invalidPeriod($period);
        }

        return new self($analyticsMetric, $analyticsGroupBy, $analyticsPeriod);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireNonEmptyString(array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw InvalidAnalyticsIntentException::missingField($key);
        }

        $value = trim($data[$key]);

        if ('' === $value) {
            throw InvalidAnalyticsIntentException::missingField($key);
        }

        return $value;
    }
}
