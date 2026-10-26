<?php

namespace App\AI\Application\Ask;

use App\AI\Domain\ValueObject\AnalyticsPeriod;

final class AnalyticsPeriodDateRange
{
    /**
     * @return array{from: non-empty-string, to: non-empty-string}
     *
     * @throws \Exception
     */
    public function toInclusiveYmd(AnalyticsPeriod $period, \DateTimeImmutable $todayUtc): array
    {
        $today = $todayUtc->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        return match ($period) {
            AnalyticsPeriod::Today => [
                'from' => $today->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            AnalyticsPeriod::LastWeek => [
                'from' => $today->sub(new \DateInterval('P6D'))->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            AnalyticsPeriod::LastMonth => [
                'from' => $today->sub(new \DateInterval('P29D'))->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
        };
    }
}
