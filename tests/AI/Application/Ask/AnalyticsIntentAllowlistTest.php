<?php

namespace App\Tests\AI\Application\Ask;

use App\AI\Application\Ask\AnalyticsIntentAllowlist;
use App\AI\Domain\Exception\UnsupportedAnalyticsIntentException;
use App\AI\Domain\ValueObject\AnalyticsGroupBy;
use App\AI\Domain\ValueObject\AnalyticsIntent;
use App\AI\Domain\ValueObject\AnalyticsMetric;
use App\AI\Domain\ValueObject\AnalyticsPeriod;
use PHPUnit\Framework\TestCase;

final class AnalyticsIntentAllowlistTest extends TestCase
{
    public function testAllowsBetsByDayForAnyPeriod(): void
    {
        $allowlist = new AnalyticsIntentAllowlist();

        foreach ([
            AnalyticsPeriod::Today,
            AnalyticsPeriod::LastWeek,
            AnalyticsPeriod::LastMonth,
        ] as $period) {
            $allowlist->assertSupported(new AnalyticsIntent(
                AnalyticsMetric::Bets,
                AnalyticsGroupBy::Day,
                $period,
            ));
        }

        $this->addToAssertionCount(1);
    }

    public function testRejectsUnsupportedMetricOrGroup(): void
    {
        $this->expectException(UnsupportedAnalyticsIntentException::class);

        new AnalyticsIntentAllowlist()->assertSupported(new AnalyticsIntent(
            AnalyticsMetric::Ggr,
            AnalyticsGroupBy::Game,
            AnalyticsPeriod::Today,
        ));
    }
}
