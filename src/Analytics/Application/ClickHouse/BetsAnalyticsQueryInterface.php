<?php

namespace App\Analytics\Application\ClickHouse;

use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Domain\Exception\ClickHouseException;

interface BetsAnalyticsQueryInterface
{
    /**
     * @return list<BetsByDayStat>
     *
     * @throws ClickHouseException
     */
    public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array;
}
