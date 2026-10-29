<?php

namespace App\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Application\Query\GgrByDayStat;
use App\Analytics\Domain\Exception\ClickHouseException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(BetsAnalyticsQueryInterface::class, public: true)]
final class ClickHouseBetsAnalyticsQuery implements BetsAnalyticsQueryInterface
{
    public function __construct(
        private readonly ClickHouseClientInterface $clickHouse,
    ) {
    }

    public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
    {
        $from = $fromInclusive->format('Y-m-d H:i:s');
        $to = $toExclusive->format('Y-m-d H:i:s');
        $table = ClickHouseSchema::BET_EVENTS_TABLE;

        $sql = <<<CH
SELECT
    toDate(occurred_at) AS day,
    count() AS bets_count,
    sum(amount_minor) AS amount_minor
FROM {$table}
WHERE occurred_at >= '$from' AND occurred_at < '$to'
GROUP BY day
ORDER BY day
CH;

        $rows = $this->clickHouse->select($sql);
        $stats = [];

        foreach ($rows as $row) {
            if (!\array_key_exists('day', $row)
                || !\array_key_exists('bets_count', $row)
                || !\array_key_exists('amount_minor', $row)
            ) {
                throw ClickHouseException::invalidResponse('expected day, bets_count and amount_minor columns');
            }

            if (!\is_string($row['day']) && !\is_numeric($row['day'])) {
                throw ClickHouseException::invalidResponse('expected day to be a string or number');
            }

            if (!\is_numeric($row['bets_count']) || !\is_numeric($row['amount_minor'])) {
                throw ClickHouseException::invalidResponse('expected bets_count and amount_minor to be numeric');
            }

            $stats[] = new BetsByDayStat(
                (string) $row['day'],
                (int) $row['bets_count'],
                (int) $row['amount_minor'],
            );
        }

        return $stats;
    }

    public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
    {
        $from = $fromInclusive->format('Y-m-d H:i:s');
        $to = $toExclusive->format('Y-m-d H:i:s');
        $table = ClickHouseSchema::BET_SETTLED_EVENTS_TABLE;
        $sql = <<<CH
SELECT
    toDate(occurred_at) AS day,
    sum(stake_minor) AS stake_minor,
    sum(payout_minor) AS payout_minor,
    sum(stake_minor) - sum(payout_minor) AS ggr_minor
FROM {$table}
WHERE occurred_at >= '$from' AND occurred_at < '$to'
GROUP BY day
ORDER BY day
CH;
        $rows = $this->clickHouse->select($sql);
        $stats = [];
        foreach ($rows as $row) {
            if (!\array_key_exists('day', $row)
                || !\array_key_exists('stake_minor', $row)
                || !\array_key_exists('payout_minor', $row)
                || !\array_key_exists('ggr_minor', $row)
            ) {
                throw ClickHouseException::invalidResponse('expected day, stake_minor, payout_minor and ggr_minor columns');
            }

            if (!\is_string($row['day']) && !\is_numeric($row['day'])) {
                throw ClickHouseException::invalidResponse('expected day to be a string or number');
            }

            if (!\is_numeric($row['stake_minor']) || !\is_numeric($row['payout_minor']) || !\is_numeric($row['ggr_minor'])) {
                throw ClickHouseException::invalidResponse('expected stake_minor, payout_minor and ggr_minor to be numeric');
            }

            $stats[] = new GgrByDayStat(
                (string) $row['day'],
                (int) $row['stake_minor'],
                (int) $row['payout_minor'],
                (int) $row['ggr_minor'],
            );
        }

        return $stats;
    }
}
