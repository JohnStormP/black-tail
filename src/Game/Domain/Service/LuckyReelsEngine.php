<?php

namespace App\Game\Domain\Service;

use App\Game\Domain\Exception\InvalidLuckyReelsSpinException;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Game\Domain\ValueObject\LuckyReelsLineWin;
use App\Game\Domain\ValueObject\LuckyReelsSpinResult;
use App\Game\Domain\ValueObject\ReelSymbol;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;

final class LuckyReelsEngine
{
    public function spin(LuckyReelsConfig $config, RandomIntGeneratorInterface $rng): LuckyReelsSpinResult
    {
        $strips = $config->reelStrips();

        $stops = [];
        for ($reel = 0; $reel < $config->reels(); ++$reel) {
            $length = \count($strips[$reel]);
            $stops[] = $rng->nextInt(0, $length - 1);
        }

        return $this->evaluateStops($config, $stops);
    }

    /**
     * @param list<int> $stops
     */
    public function evaluateStops(LuckyReelsConfig $config, array $stops): LuckyReelsSpinResult
    {
        if (\count($stops) !== $config->reels()) {
            throw InvalidLuckyReelsSpinException::invalidStopCount($config->reels(), \count($stops));
        }

        $strips = $config->reelStrips();

        foreach ($stops as $reel => $stop) {
            $stripLength = \count($strips[$reel]);
            if ($stop < 0 || $stop >= $stripLength) {
                throw InvalidLuckyReelsSpinException::stopOutOfRange($reel, $stop, $stripLength);
            }
        }

        $rows = $config->rows();
        $grid = [];
        foreach ($stops as $reel => $stop) {
            $strip = $strips[$reel];
            $stripLength = \count($strip);
            $column = [];
            for ($row = 0; $row < $rows; ++$row) {
                $column[] = $strip[($stop + $row) % $stripLength];
            }
            $grid[] = $column;
        }

        $lineWins = [];
        $totalMultiplier = 0;
        foreach ($config->paylines() as $paylineIndex => $line) {
            $symbols = [];
            foreach ($line as $reel => $row) {
                $symbols[] = $grid[$reel][$row];
            }

            [$symbol, $matchCount] = $this->matchLine($symbols);

            if ($matchCount < 3) {
                continue;
            }

            $multiplier = $config->multiplierFor($symbol, $matchCount);
            if ($multiplier <= 0) {
                continue;
            }

            $lineWins[] = new LuckyReelsLineWin($paylineIndex, $symbol, $matchCount, $multiplier);
            $totalMultiplier += $multiplier;
        }

        return new LuckyReelsSpinResult($stops, $grid, $lineWins, $totalMultiplier);
    }

    /**
     * @param list<ReelSymbol> $symbols
     *
     * @return array{0: ReelSymbol, 1: int}
     */
    private function matchLine(array $symbols): array
    {
        $allWild = true;
        foreach ($symbols as $s) {
            if (ReelSymbol::BlackTail !== $s) {
                $allWild = false;
                break;
            }
        }
        if ($allWild) {
            return [ReelSymbol::BlackTail, \count($symbols)];
        }

        $target = null;
        $count = 0;
        foreach ($symbols as $s) {
            if (ReelSymbol::BlackTail === $s) {
                ++$count;
                continue;
            }
            if (null === $target) {
                $target = $s;
                ++$count;
                continue;
            }
            if ($s === $target) {
                ++$count;
                continue;
            }
            break;
        }

        return [$target ?? ReelSymbol::BlackTail, $count];
    }
}
