<?php

namespace App\Analytics\Infrastructure\Http;

use App\Analytics\Application\Query\GgrByDayStat;

final class GgrByDayJson
{
    /**
     * @param list<GgrByDayStat> $stats
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     days: list<array{day: string, stakeMinor: int, payoutMinor: int, ggrMinor: int}>
     * }
     */
    public static function fromStats(string $from, string $to, array $stats): array
    {
        $days = [];
        foreach ($stats as $stat) {
            $days[] = [
                'day' => $stat->day,
                'stakeMinor' => $stat->stakeMinor,
                'payoutMinor' => $stat->payoutMinor,
                'ggrMinor' => $stat->ggrMinor,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
        ];
    }
}
