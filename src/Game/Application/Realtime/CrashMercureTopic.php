<?php

namespace App\Game\Application\Realtime;

use App\Game\Domain\ValueObject\CrashRoundId;

final class CrashMercureTopic
{
    public static function forRound(CrashRoundId $roundId): string
    {
        return 'https://black-tail.local/crash/'.$roundId->toString();
    }
}
