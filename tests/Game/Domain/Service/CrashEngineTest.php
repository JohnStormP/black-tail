<?php

namespace App\Tests\Game\Domain\Service;

use App\Game\Domain\Service\CrashEngine;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use PHPUnit\Framework\TestCase;

final class CrashEngineTest extends TestCase
{
    public function testRollCrashPointIsDeterministicForSameSeed(): void
    {
        $engine = new CrashEngine();

        $a = $engine->rollCrashPoint(new SeededRandomIntGenerator(42));
        $b = $engine->rollCrashPoint(new SeededRandomIntGenerator(42));

        $this->assertTrue($a->equals($b));
        $this->assertGreaterThanOrEqual(CrashMultiplier::MIN_HUNDREDTHS, $a->toHundredths());
        $this->assertLessThanOrEqual(CrashMultiplier::MAX_HUNDREDTHS, $a->toHundredths());
    }

    public function testRollCrashPointDiffersForDifferentSeeds(): void
    {
        $engine = new CrashEngine();
        $a = $engine->rollCrashPoint(new SeededRandomIntGenerator(1));
        $b = $engine->rollCrashPoint(new SeededRandomIntGenerator(2));

        $this->assertFalse($a->equals($b));
    }
}
