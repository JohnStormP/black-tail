<?php

namespace App\Game\Domain\Service;

use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;

final class CrashEngine
{
    private const int RAW_MAX = 1_000_000;
    private const int NUMERATOR = 99_000_000;

    public function rollCrashPoint(RandomIntGeneratorInterface $rng): CrashMultiplier
    {
        $raw = $rng->nextInt(1, self::RAW_MAX);
        $hundredths = intdiv(self::NUMERATOR, $raw);
        $max = CrashMultiplier::MAX_HUNDREDTHS;
        $min = CrashMultiplier::MIN_HUNDREDTHS;
        $hundredths = max($min, min($max, $hundredths));

        return CrashMultiplier::fromHundredths($hundredths);
    }
}
