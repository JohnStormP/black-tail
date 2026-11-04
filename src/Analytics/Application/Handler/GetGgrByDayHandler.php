<?php

namespace App\Analytics\Application\Handler;

use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Query\GetGgrByDayQuery;
use App\Analytics\Application\Query\GgrByDayStat;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;

final class GetGgrByDayHandler
{
    public function __construct(
        private readonly BetsAnalyticsQueryInterface $betsAnalytics,
    ) {
    }

    /**
     * @return list<GgrByDayStat>
     */
    public function __invoke(GetGgrByDayQuery $query): array
    {
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->fromDate);
        if (false === $from) {
            throw InvalidAnalyticsPeriodException::invalidDate($query->fromDate);
        }

        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->toDate);
        if (false === $to) {
            throw InvalidAnalyticsPeriodException::invalidDate($query->toDate);
        }

        if ($from > $to) {
            throw InvalidAnalyticsPeriodException::invertedRange($query->fromDate, $query->toDate);
        }

        $toExclusive = $to->add(new \DateInterval('P1D'));

        return $this->betsAnalytics->ggrByDay($from, $toExclusive, $query->tenantId);
    }
}
