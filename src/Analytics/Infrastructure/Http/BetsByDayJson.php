<?php

namespace App\Analytics\Infrastructure\Http;

use App\Analytics\Application\Query\BetsByDayStat;

final class BetsByDayJson
{
    /**
     * @param list<BetsByDayStat> $stats
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     days: list<array{day: string, betsCount: int, amountMinor: int}>
     * }
     */
    public static function fromStats(string $from, string $to, array $stats): array
    {
        $days = [];
        foreach ($stats as $stat) {
            $days[] = [
                'day' => $stat->day,
                'betsCount' => $stat->betsCount,
                'amountMinor' => $stat->amountMinor,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
        ];
    }
}
