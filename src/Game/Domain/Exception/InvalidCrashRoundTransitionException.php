<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\CrashRoundStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidCrashRoundTransitionException extends DomainException
{
    public static function cannotTransition(CrashRoundStatus $from, CrashRoundStatus $to): self
    {
        return new self(
            'Cannot transition crash round from "'.$from->value.'" to "'.$to->value.'"'
        );
    }

    public static function roundNotRunning(string $roundId, CrashRoundStatus $status): self
    {
        return new self(
            'Crash round "'.$roundId.'" must be running to read flight multiplier, current status "'.$status->value.'"'
        );
    }

    public static function roundMissingStartedAt(string $roundId): self
    {
        return new self('Crash round "'.$roundId.'" is running but started_at is missing');
    }
}
