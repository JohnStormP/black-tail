<?php

namespace App\Tests\Analytics\Application\Handler;

use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Handler\GetBetsByDayHandler;
use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Application\Query\GetBetsByDayQuery;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class GetBetsByDayHandlerTest extends TestCase
{
    public function testReturnsStatsForValidPeriod(): void
    {
        $analytics = new BetsAnalyticsQuerySpy([
            new BetsByDayStat('2026-10-20', 2, 150),
        ]);
        $handler = new GetBetsByDayHandler($analytics);
        $stats = ($handler)(new GetBetsByDayQuery('2026-10-20', '2026-10-21', '01EXYZ00000000000000000099'));

        $this->assertEquals([
            new BetsByDayStat('2026-10-20', 2, 150),
        ], $stats);

        $this->assertEquals(new \DateTimeImmutable('2026-10-20 00:00:00'), $analytics->lastFrom);
        $this->assertEquals(new \DateTimeImmutable('2026-10-22 00:00:00'), $analytics->lastTo);
    }

    public function testRejectsInvalidDate(): void
    {
        $handler = new GetBetsByDayHandler(new BetsAnalyticsQuerySpy());

        $this->expectException(InvalidAnalyticsPeriodException::class);
        ($handler)(new GetBetsByDayQuery('not-a-date', '2026-10-21', '01EXYZ00000000000000000099'));
    }

    public function testRejectsInvertedRange(): void
    {
        $handler = new GetBetsByDayHandler(new BetsAnalyticsQuerySpy());

        $this->expectException(InvalidAnalyticsPeriodException::class);
        ($handler)(new GetBetsByDayQuery('2026-10-22', '2026-10-20', '01EXYZ00000000000000000099'));
    }
}

final class BetsAnalyticsQuerySpy implements BetsAnalyticsQueryInterface
{
    public ?\DateTimeImmutable $lastFrom = null;
    public ?\DateTimeImmutable $lastTo = null;

    /**
     * @param list<BetsByDayStat> $result
     */
    public function __construct(
        private readonly array $result = [],
    ) {
    }

    public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
    {
        if ([] === $this->result) {
            Assert::fail('ClickHouse query must not be called for invalid input');
        }

        $this->lastFrom = $fromInclusive;
        $this->lastTo = $toExclusive;

        return $this->result;
    }

    public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
    {
        Assert::fail('ggrByDay must not be called from GetBetsByDayHandler');
    }
}
