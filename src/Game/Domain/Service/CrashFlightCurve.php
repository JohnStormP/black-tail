<?php

namespace App\Game\Domain\Service;

use App\Game\Domain\ValueObject\CrashMultiplier;

final class CrashFlightCurve
{
    public const int MS_PER_HUNDREDTH = 50;

    private function __construct()
    {
    }

    public static function atElapsedMs(int $elapsedMs, CrashMultiplier $crashPoint): CrashMultiplier
    {
        $elapsedMs = max(0, $elapsedMs);
        $grown = CrashMultiplier::MIN_HUNDREDTHS + intdiv($elapsedMs, self::MS_PER_HUNDREDTH);
        $capped = min($grown, $crashPoint->toHundredths(), CrashMultiplier::MAX_HUNDREDTHS);

        return CrashMultiplier::fromHundredths($capped);
    }
}
