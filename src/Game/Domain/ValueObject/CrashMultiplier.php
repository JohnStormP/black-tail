<?php

namespace App\Game\Domain\ValueObject;

use App\Game\Domain\Exception\InvalidCrashMultiplierException;

final readonly class CrashMultiplier
{
    public const int MIN_HUNDREDTHS = 100;
    public const int MAX_HUNDREDTHS = 10_000;

    private function __construct(
        private int $hundredths,
    ) {
    }

    public static function fromHundredths(int $hundredths): self
    {
        if ($hundredths < self::MIN_HUNDREDTHS) {
            throw InvalidCrashMultiplierException::belowMinimum($hundredths);
        }

        if ($hundredths > self::MAX_HUNDREDTHS) {
            throw InvalidCrashMultiplierException::aboveMaximum($hundredths, self::MAX_HUNDREDTHS);
        }

        return new self($hundredths);
    }

    public function toHundredths(): int
    {
        return $this->hundredths;
    }

    public function isStrictlyBelow(self $other): bool
    {
        return $this->hundredths < $other->hundredths;
    }

    public function equals(self $other): bool
    {
        return $this->hundredths === $other->hundredths;
    }

    public function payoutMinor(int $betMinor): int
    {
        if ($betMinor <= 0) {
            throw InvalidCrashMultiplierException::nonPositiveBet($betMinor);
        }

        return intdiv($betMinor * $this->hundredths, 100);
    }
}
