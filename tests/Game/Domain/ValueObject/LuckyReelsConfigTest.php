<?php

namespace App\Tests\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidLuckyReelsConfigException;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Game\Domain\ValueObject\ReelSymbol;
use PHPUnit\Framework\TestCase;

final class LuckyReelsConfigTest extends TestCase
{
    public function testDefaultShape(): void
    {
        $config = LuckyReelsConfig::default();

        $this->assertSame(5, $config->reels());
        $this->assertSame(3, $config->rows());
        $this->assertCount(5, $config->reelStrips());
        $this->assertSame([
            [0, 0, 0, 0, 0],
            [1, 1, 1, 1, 1],
            [2, 2, 2, 2, 2],
        ], $config->paylines());
        $this->assertSame(10, $config->minBetMinor());
        $this->assertSame(10_000, $config->maxBetMinor());

        foreach ($config->reelStrips() as $strip) {
            $this->assertNotSame([], $strip);
            $this->assertGreaterThanOrEqual(3, count($strip));
            foreach ($strip as $symbol) {
                $this->assertInstanceOf(ReelSymbol::class, $symbol);
            }
        }
    }

    public function testDefaultPaytableCoversAllSymbols(): void
    {
        $config = LuckyReelsConfig::default();
        $paytable = $config->paytable();

        foreach (ReelSymbol::cases() as $symbol) {
            $this->assertArrayHasKey($symbol->value, $paytable);
            $this->assertGreaterThan(0, $config->multiplierFor($symbol, 3));
            $this->assertGreaterThan(0, $config->multiplierFor($symbol, 4));
            $this->assertGreaterThan(0, $config->multiplierFor($symbol, 5));
            $this->assertSame(0, $config->multiplierFor($symbol, 2));
        }

        $this->assertSame(1, $config->multiplierFor(ReelSymbol::Siamese, 3));
        $this->assertSame(80, $config->multiplierFor(ReelSymbol::BlackTail, 5));
    }

    public function testIsBetAllowed(): void
    {
        $config = LuckyReelsConfig::default();

        $this->assertTrue($config->isBetAllowed(10));
        $this->assertTrue($config->isBetAllowed(10_000));
        $this->assertFalse($config->isBetAllowed(9));
        $this->assertFalse($config->isBetAllowed(10_001));
    }

    public function testCreateRejectsWrongReelCount(): void
    {
        $this->expectException(InvalidLuckyReelsConfigException::class);

        LuckyReelsConfig::create(
            reelStrips: [[ReelSymbol::Siamese]],
            paylines: $this->validPaylines(),
            paytable: $this->validPaytable(),
            minBetMinor: 10,
            maxBetMinor: 100,
        );
    }

    public function testCreateRejectsInvalidBetLimits(): void
    {
        $strips = $this->minimalStrips();

        $this->expectException(InvalidLuckyReelsConfigException::class);

        LuckyReelsConfig::create(
            reelStrips: $strips,
            paylines: $this->validPaylines(),
            paytable: $this->validPaytable(),
            minBetMinor: 0,
            maxBetMinor: 100,
        );
    }

    public function testCreateRejectsMissingPaytableSymbol(): void
    {
        $paytable = $this->validPaytable();
        unset($paytable[ReelSymbol::BlackTail->value]);

        $this->expectException(InvalidLuckyReelsConfigException::class);

        LuckyReelsConfig::create(
            reelStrips: $this->minimalStrips(),
            paylines: $this->validPaylines(),
            paytable: $paytable,
            minBetMinor: 10,
            maxBetMinor: 100,
        );
    }

    /**
     * @return list<list<ReelSymbol>>
     */
    private function minimalStrips(): array
    {
        $strip = [ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Maine];

        return [$strip, $strip, $strip, $strip, $strip];
    }

    /**
     * @return list<list<int>>
     */
    private function validPaylines(): array
    {
        return [
            [0, 0, 0, 0, 0],
            [1, 1, 1, 1, 1],
            [2, 2, 2, 2, 2],
        ];
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function validPaytable(): array
    {
        return [
            'siamese' => [3 => 1, 4 => 2, 5 => 4],
            'persian' => [3 => 1, 4 => 3, 5 => 6],
            'maine' => [3 => 2, 4 => 5, 5 => 12],
            'bengal' => [3 => 3, 4 => 7, 5 => 22],
            'sphynx' => [3 => 4, 4 => 12, 5 => 45],
            'black_tail' => [3 => 6, 4 => 20, 5 => 80],
        ];
    }
}
