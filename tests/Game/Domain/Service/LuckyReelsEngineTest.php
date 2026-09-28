<?php

namespace App\Tests\Game\Domain\Service;

use App\Game\Domain\Exception\InvalidLuckyReelsSpinException;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Game\Domain\ValueObject\ReelSymbol;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use PHPUnit\Framework\TestCase;

final class LuckyReelsEngineTest extends TestCase
{
    private LuckyReelsEngine $engine;
    private LuckyReelsConfig $config;

    protected function setUp(): void
    {
        $this->engine = new LuckyReelsEngine();
        $this->config = LuckyReelsConfig::default();
    }

    public function testEvaluateStopsBuildsGridFromCircularStrip(): void
    {
        // All stops at 0 → each reel shows strip[0], strip[1], strip[2]
        $result = $this->engine->evaluateStops($this->config, [0, 0, 0, 0, 0]);

        $this->assertSame([0, 0, 0, 0, 0], $result->stops());
        $strips = $this->config->reelStrips();
        foreach ($strips as $reel => $strip) {
            $this->assertSame($strip[0], $result->grid()[$reel][0]);
            $this->assertSame($strip[1], $result->grid()[$reel][1]);
            $this->assertSame($strip[2], $result->grid()[$reel][2]);
        }
    }

    public function testMiddleLineAllSiamesePays(): void
    {
        // Craft stops so middle row (row 1) is five Siamese if possible.
        // Fallback: use known-winning synthetic via evaluate on forced grid path —
        // here we pick stops where strip[stop+1] is Siamese on every reel.
        $stops = [];
        foreach ($this->config->reelStrips() as $reel => $strip) {
            $length = count($strip);
            $found = null;
            for ($stop = 0; $stop < $length; ++$stop) {
                if (ReelSymbol::Siamese === $strip[($stop + 1) % $length]) {
                    $found = $stop;
                    break;
                }
            }
            $this->assertNotNull($found, 'reel '.$reel.' must have Siamese for middle row');
            $stops[] = $found;
        }

        $result = $this->engine->evaluateStops($this->config, $stops);

        $middleWins = array_values(array_filter(
            $result->lineWins(),
            static fn ($win) => 1 === $win->paylineIndex,
        ));
        $this->assertNotSame([], $middleWins);
        $this->assertSame(ReelSymbol::Siamese, $middleWins[0]->symbol);
        $this->assertGreaterThanOrEqual(3, $middleWins[0]->matchCount);
        $this->assertTrue($result->isWin());
        $this->assertSame(100 * $result->totalMultiplier(), $result->winAmountMinor(100));
    }

    public function testSpinIsDeterministicWithSeed(): void
    {
        $a = $this->engine->spin($this->config, new SeededRandomIntGenerator(7));
        $b = $this->engine->spin($this->config, new SeededRandomIntGenerator(7));

        $this->assertSame($a->stops(), $b->stops());
        $this->assertSame($a->totalMultiplier(), $b->totalMultiplier());
        $this->assertEquals($a->grid(), $b->grid());
    }

    public function testRejectsWrongStopCount(): void
    {
        $this->expectException(InvalidLuckyReelsSpinException::class);

        $this->engine->evaluateStops($this->config, [0, 0, 0]);
    }

    public function testRejectsStopOutOfRange(): void
    {
        $this->expectException(InvalidLuckyReelsSpinException::class);

        $this->engine->evaluateStops($this->config, [0, 0, 0, 0, 999]);
    }

    public function testAllBlackTailLineUsesWildPaytable(): void
    {
        // Build minimal config: strips of only BlackTail, one payline middle
        $strip = [
            ReelSymbol::BlackTail,
            ReelSymbol::BlackTail,
            ReelSymbol::BlackTail,
        ];
        $config = LuckyReelsConfig::create(
            reelStrips: [$strip, $strip, $strip, $strip, $strip],
            paylines: [[1, 1, 1, 1, 1]],
            paytable: [
                'siamese' => [3 => 1, 4 => 2, 5 => 4],
                'persian' => [3 => 1, 4 => 3, 5 => 6],
                'maine' => [3 => 2, 4 => 5, 5 => 12],
                'bengal' => [3 => 3, 4 => 7, 5 => 22],
                'sphynx' => [3 => 4, 4 => 12, 5 => 45],
                'black_tail' => [3 => 6, 4 => 20, 5 => 80],
            ],
            minBetMinor: 10,
            maxBetMinor: 100,
        );

        $result = $this->engine->evaluateStops($config, [0, 0, 0, 0, 0]);

        $this->assertCount(1, $result->lineWins());
        $this->assertSame(ReelSymbol::BlackTail, $result->lineWins()[0]->symbol);
        $this->assertSame(5, $result->lineWins()[0]->matchCount);
        $this->assertSame(80, $result->lineWins()[0]->multiplier);
        $this->assertSame(80, $result->totalMultiplier());
    }
}
