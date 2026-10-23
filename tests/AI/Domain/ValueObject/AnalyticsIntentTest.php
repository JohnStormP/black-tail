<?php

namespace App\Tests\AI\Domain\ValueObject;

use App\AI\Domain\Exception\InvalidAnalyticsIntentException;
use App\AI\Domain\ValueObject\AnalyticsGroupBy;
use App\AI\Domain\ValueObject\AnalyticsIntent;
use App\AI\Domain\ValueObject\AnalyticsMetric;
use App\AI\Domain\ValueObject\AnalyticsPeriod;
use PHPUnit\Framework\TestCase;

final class AnalyticsIntentTest extends TestCase
{
    public function testFromArrayBuildsIntent(): void
    {
        $intent = AnalyticsIntent::fromArray([
            'metric' => 'BETS',
            'group_by' => 'DAY',
            'period' => 'LAST_WEEK',
        ]);
        $this->assertSame(AnalyticsMetric::Bets, $intent->metric);
        $this->assertSame(AnalyticsGroupBy::Day, $intent->groupBy);
        $this->assertSame(AnalyticsPeriod::LastWeek, $intent->period);
    }

    public function testFromArrayRejectsUnknownMetric(): void
    {
        $this->expectException(InvalidAnalyticsIntentException::class);
        AnalyticsIntent::fromArray([
            'metric' => 'SQL_INJECTION',
            'group_by' => 'DAY',
            'period' => 'LAST_WEEK',
        ]);
    }
}
