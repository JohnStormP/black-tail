<?php

namespace App\Analytics\Application\ClickHouse;

use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Application\Query\GgrByDayStat;
use App\Analytics\Domain\Exception\ClickHouseException;

interface BetsAnalyticsQueryInterface
{
    /**
     * @return list<BetsByDayStat>
     *
     * @throws ClickHouseException
     */
    public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array;

    /**
     * Gross Gaming Revenue by day: sum(stake_minor) - sum(payout_minor) from settled bets.
     *
     * @return list<GgrByDayStat>
     *
     * @throws ClickHouseException
     */
    public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array;
}
