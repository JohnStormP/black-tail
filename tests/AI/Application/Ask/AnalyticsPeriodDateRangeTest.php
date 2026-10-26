<?php

namespace App\Tests\AI\Application\Ask;

use App\AI\Application\Ask\AnalyticsPeriodDateRange;
use App\AI\Domain\ValueObject\AnalyticsPeriod;
use PHPUnit\Framework\TestCase;

final class AnalyticsPeriodDateRangeTest extends TestCase
{
    public function testTodayIsSingleDay(): void
    {
        $range = new AnalyticsPeriodDateRange()->toInclusiveYmd(
            AnalyticsPeriod::Today,
            new \DateTimeImmutable('2026-10-21 15:30:00', new \DateTimeZone('UTC')),
        );

        $this->assertSame(['from' => '2026-10-21', 'to' => '2026-10-21'], $range);
    }

    public function testLastWeekIsSevenInclusiveDays(): void
    {
        $range = new AnalyticsPeriodDateRange()->toInclusiveYmd(
            AnalyticsPeriod::LastWeek,
            new \DateTimeImmutable('2026-10-21 00:00:00', new \DateTimeZone('UTC')),
        );

        $this->assertSame(['from' => '2026-10-15', 'to' => '2026-10-21'], $range);
    }

    public function testLastMonthIsThirtyInclusiveDays(): void
    {
        $range = new AnalyticsPeriodDateRange()->toInclusiveYmd(
            AnalyticsPeriod::LastMonth,
            new \DateTimeImmutable('2026-10-21 12:00:00', new \DateTimeZone('UTC')),
        );

        $this->assertSame(['from' => '2026-09-22', 'to' => '2026-10-21'], $range);
    }
}
