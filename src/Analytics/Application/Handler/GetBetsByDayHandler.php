<?php

namespace App\Analytics\Application\Handler;

use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Application\Query\GetBetsByDayQuery;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;

final class GetBetsByDayHandler
{
    public function __construct(
        private readonly BetsAnalyticsQueryInterface $betsAnalytics,
    ) {
    }

    /**
     * @return list<BetsByDayStat>
     */
    public function __invoke(GetBetsByDayQuery $query): array
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

        return $this->betsAnalytics->betsByDay($from, $toExclusive);
    }
}
