<?php

namespace App\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidLuckyReelsConfigException;

/**
 * Immutable math config for Lucky Reels (5×3).
 */
final readonly class LuckyReelsConfig
{
    public const int REELS = 5;
    public const int ROWS = 3;

    /**
     * @param list<list<ReelSymbol>>         $reelStrips
     * @param list<list<int>>                $paylines
     * @param array<string, array<int, int>> $paytable
     */
    private function __construct(
        private array $reelStrips,
        private array $paylines,
        private array $paytable,
        private int $minBetMinor,
        private int $maxBetMinor,
    ) {
    }

    public static function default(): self
    {
        return self::create(
            reelStrips: self::defaultReelStrips(),
            paylines: self::defaultPaylines(),
            paytable: self::defaultPaytable(),
            minBetMinor: 10,
            maxBetMinor: 10_000,
        );
    }

    /**
     * @param list<list<ReelSymbol>>         $reelStrips
     * @param list<list<int>>                $paylines
     * @param array<string, array<int, int>> $paytable
     */
    public static function create(
        array $reelStrips,
        array $paylines,
        array $paytable,
        int $minBetMinor,
        int $maxBetMinor,
    ): self {
        if (self::REELS !== \count($reelStrips)) {
            throw InvalidLuckyReelsConfigException::invalidReelCount(\count($reelStrips));
        }

        foreach ($reelStrips as $i => $strip) {
            if ([] === $strip) {
                throw InvalidLuckyReelsConfigException::emptyReelStrip($i);
            }

            $length = \count($strip);
            if ($length < self::ROWS) {
                throw InvalidLuckyReelsConfigException::stripShorterThanRows($i, $length, self::ROWS);
            }
        }

        if ([] === $paylines) {
            throw InvalidLuckyReelsConfigException::invalidPaylines('list cannot be empty');
        }

        foreach ($paylines as $lineIndex => $line) {
            if (self::REELS !== \count($line)) {
                throw InvalidLuckyReelsConfigException::invalidPaylines('line #'.$lineIndex.' must have '.self::REELS.' row indexes, got '.\count($line));
            }

            foreach ($line as $reelIndex => $rowIndex) {
                if ($rowIndex < 0 || $rowIndex >= self::ROWS) {
                    throw InvalidLuckyReelsConfigException::invalidPaylines('line #'.$lineIndex.' reel #'.$reelIndex.' has invalid row index');
                }
            }
        }

        if ($minBetMinor <= 0 || $maxBetMinor < $minBetMinor) {
            throw InvalidLuckyReelsConfigException::invalidBetLimits($minBetMinor, $maxBetMinor);
        }

        if ([] === $paytable) {
            throw InvalidLuckyReelsConfigException::invalidPaytableEntry('cannot be empty');
        }

        foreach ($paytable as $symbol => $counts) {
            if (ReelSymbol::tryFrom($symbol)?->value !== $symbol) {
                throw InvalidLuckyReelsConfigException::invalidPaytableEntry('unknown symbol "'.$symbol.'"');
            }

            foreach ($counts as $matchCount => $multiplier) {
                if (!\in_array($matchCount, [3, 4, 5], true)) {
                    throw InvalidLuckyReelsConfigException::invalidPaytableEntry('symbol "'.$symbol.'" has invalid match count '.$matchCount);
                }

                if ($multiplier <= 0) {
                    throw InvalidLuckyReelsConfigException::invalidPaytableEntry('symbol "'.$symbol.'" match count '.$matchCount.' must have multiplier > 0');
                }
            }
        }

        foreach (ReelSymbol::cases() as $case) {
            if (!\array_key_exists($case->value, $paytable)) {
                throw InvalidLuckyReelsConfigException::invalidPaytableEntry('missing symbol "'.$case->value.'"');
            }
        }

        return new self($reelStrips, $paylines, $paytable, $minBetMinor, $maxBetMinor);
    }

    /**
     * @return list<list<ReelSymbol>>
     */
    private static function defaultReelStrips(): array
    {
        return [
            [
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese,
                ReelSymbol::Maine, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Bengal,
                ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Maine,
                ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Sphynx, ReelSymbol::Persian, ReelSymbol::BlackTail,
            ],
            [
                ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian,
                ReelSymbol::Maine, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Bengal,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Maine,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Sphynx, ReelSymbol::Siamese, ReelSymbol::BlackTail,
            ],
            [
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Maine, ReelSymbol::Siamese, ReelSymbol::Persian,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Bengal, ReelSymbol::Persian,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Maine, ReelSymbol::Siamese, ReelSymbol::Persian,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Sphynx, ReelSymbol::Siamese, ReelSymbol::BlackTail,
            ],
            [
                ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Bengal, ReelSymbol::Persian, ReelSymbol::Siamese,
                ReelSymbol::Persian, ReelSymbol::Maine, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese,
                ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Maine, ReelSymbol::Persian, ReelSymbol::Siamese,
                ReelSymbol::Bengal, ReelSymbol::Persian, ReelSymbol::Sphynx, ReelSymbol::Siamese, ReelSymbol::BlackTail,
            ],
            [
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Maine,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Bengal,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Maine,
                ReelSymbol::Siamese, ReelSymbol::Persian, ReelSymbol::Sphynx, ReelSymbol::Siamese, ReelSymbol::BlackTail,
            ],
        ];
    }

    /**
     * @return list<list<int>>
     */
    private static function defaultPaylines(): array
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
    private static function defaultPaytable(): array
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

    public function reels(): int
    {
        return self::REELS;
    }

    public function rows(): int
    {
        return self::ROWS;
    }

    /**
     * @return list<list<ReelSymbol>>
     */
    public function reelStrips(): array
    {
        return $this->reelStrips;
    }

    /**
     * @return list<list<int>>
     */
    public function paylines(): array
    {
        return $this->paylines;
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function paytable(): array
    {
        return $this->paytable;
    }

    public function minBetMinor(): int
    {
        return $this->minBetMinor;
    }

    public function maxBetMinor(): int
    {
        return $this->maxBetMinor;
    }

    public function isBetAllowed(int $betMinor): bool
    {
        return $betMinor >= $this->minBetMinor && $betMinor <= $this->maxBetMinor;
    }

    public function multiplierFor(ReelSymbol $symbol, int $matchCount): int
    {
        return $this->paytable[$symbol->value][$matchCount] ?? 0;
    }
}
