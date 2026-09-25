<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidLuckyReelsConfigException extends DomainException
{
    public static function invalidReelCount(int $count): self
    {
        return new self('Lucky Reels must have exactly 5 reel strips, got '.$count);
    }

    public static function emptyReelStrip(int $reelIndex): self
    {
        return new self('Lucky Reels reel strip #'.$reelIndex.' cannot be empty');
    }

    public static function stripShorterThanRows(int $reelIndex, int $length, int $rows): self
    {
        return new self(
            'Lucky Reels reel strip #'.$reelIndex.' length '.$length.' is shorter than rows '.$rows
        );
    }

    public static function invalidBetLimits(int $minBetMinor, int $maxBetMinor): self
    {
        return new self(
            'Lucky Reels bet limits invalid: min='.$minBetMinor.', max='.$maxBetMinor
        );
    }

    public static function invalidPaylines(string $detail): self
    {
        return new self('Lucky Reels paylines invalid: '.$detail);
    }

    public static function invalidPaytableEntry(string $detail): self
    {
        return new self('Lucky Reels paytable invalid: '.$detail);
    }
}
