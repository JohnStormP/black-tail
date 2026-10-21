<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidCrashMultiplierException;
use App\Game\Domain\ValueObject\CrashMultiplier;
use PHPUnit\Framework\TestCase;

final class CrashMultiplierTest extends TestCase
{
    public function testFromHundredthsStoresValue(): void
    {
        $m = CrashMultiplier::fromHundredths(250);

        $this->assertSame(250, $m->toHundredths());
    }

    public function testRejectsBelowOneX(): void
    {
        $this->expectException(InvalidCrashMultiplierException::class);

        CrashMultiplier::fromHundredths(99);
    }

    public function testRejectsAboveMax(): void
    {
        $this->expectException(InvalidCrashMultiplierException::class);

        CrashMultiplier::fromHundredths(CrashMultiplier::MAX_HUNDREDTHS + 1);
    }

    public function testIsStrictlyBelow(): void
    {
        $low = CrashMultiplier::fromHundredths(150);
        $high = CrashMultiplier::fromHundredths(200);

        $this->assertTrue($low->isStrictlyBelow($high));
        $this->assertFalse($high->isStrictlyBelow($low));
        $this->assertFalse($low->isStrictlyBelow($low));
    }

    public function testPayoutMinorUsesIntegerDivision(): void
    {
        $this->assertSame(250, CrashMultiplier::fromHundredths(250)->payoutMinor(100));
        // 1.00x on 99 = 99
        $this->assertSame(99, CrashMultiplier::fromHundredths(100)->payoutMinor(99));
    }

    public function testPayoutRejectsNonPositiveBet(): void
    {
        $this->expectException(InvalidCrashMultiplierException::class);

        CrashMultiplier::fromHundredths(200)->payoutMinor(0);
    }
}
