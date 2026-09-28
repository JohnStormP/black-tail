<?php

namespace App\Game\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidLuckyReelsSpinException extends DomainException
{
    public static function invalidStopCount(int $expected, int $actual): self
    {
        return new self('Lucky Reels expects '.$expected.' reel stops, got '.$actual);
    }

    public static function stopOutOfRange(int $reelIndex, int $stop, int $stripLength): self
    {
        return new self(
            'Lucky Reels stop '.$stop.' for reel #'.$reelIndex.' is out of range for strip length '.$stripLength
        );
    }
}
