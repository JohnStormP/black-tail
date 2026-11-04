<?php

namespace App\Tests\Game\Domain\Service;

use App\Game\Domain\Service\CrashFlightCurve;
use App\Game\Domain\ValueObject\CrashMultiplier;
use PHPUnit\Framework\TestCase;

final class CrashFlightCurveTest extends TestCase
{
    public function testStartsAtOneTimesAtZeroElapsed(): void
    {
        $at = CrashFlightCurve::atElapsedMs(0, CrashMultiplier::fromHundredths(200));
        $this->assertSame(100, $at->toHundredths());
    }

    public function testGrowsOneHundredthEveryFiftyMs(): void
    {
        $at = CrashFlightCurve::atElapsedMs(500, CrashMultiplier::fromHundredths(200));
        $this->assertSame(110, $at->toHundredths());
    }

    public function testCapsAtCrashPoint(): void
    {
        $at = CrashFlightCurve::atElapsedMs(1_000_000, CrashMultiplier::fromHundredths(150));
        $this->assertSame(150, $at->toHundredths());
    }
}
