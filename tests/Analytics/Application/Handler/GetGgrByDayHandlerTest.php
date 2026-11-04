<?php

namespace App\Tests\Analytics\Application\Handler;

use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Handler\GetGgrByDayHandler;
use App\Analytics\Application\Query\GetGgrByDayQuery;
use App\Analytics\Application\Query\GgrByDayStat;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class GetGgrByDayHandlerTest extends TestCase
{
    public function testReturnsStatsForValidPeriod(): void
    {
        $analytics = new GgrAnalyticsQuerySpy([
            new GgrByDayStat('2026-10-20', 300, 100, 200),
        ]);
        $handler = new GetGgrByDayHandler($analytics);
        $stats = ($handler)(new GetGgrByDayQuery('2026-10-20', '2026-10-21', '01EXYZ00000000000000000099'));

        $this->assertEquals([
            new GgrByDayStat('2026-10-20', 300, 100, 200),
        ], $stats);

        $this->assertEquals(new \DateTimeImmutable('2026-10-20 00:00:00'), $analytics->lastFrom);
        $this->assertEquals(new \DateTimeImmutable('2026-10-22 00:00:00'), $analytics->lastTo);
    }

    public function testRejectsInvertedRange(): void
    {
        $handler = new GetGgrByDayHandler(new GgrAnalyticsQuerySpy());

        $this->expectException(InvalidAnalyticsPeriodException::class);
        ($handler)(new GetGgrByDayQuery('2026-10-22', '2026-10-20', '01EXYZ00000000000000000099'));
    }
}

final class GgrAnalyticsQuerySpy implements BetsAnalyticsQueryInterface
{
    public ?\DateTimeImmutable $lastFrom = null;
    public ?\DateTimeImmutable $lastTo = null;

    /**
     * @param list<GgrByDayStat> $result
     */
    public function __construct(
        private readonly array $result = [],
    ) {
    }

    public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
    {
        Assert::fail('betsByDay must not be called from GetGgrByDayHandler');
    }

    public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
    {
        if ([] === $this->result) {
            Assert::fail('ClickHouse query must not be called for invalid input');
        }

        $this->lastFrom = $fromInclusive;
        $this->lastTo = $toExclusive;

        return $this->result;
    }
}
