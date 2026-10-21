<?php

namespace App\Game\Application\Realtime;

use App\Game\Domain\ValueObject\CrashRoundId;

interface CrashRealtimePublisherInterface
{
    public function publishStarted(CrashRoundId $roundId): void;

    public function publishTick(CrashRoundId $roundId, int $hundredths): void;

    public function publishCrashed(CrashRoundId $roundId, int $crashPointHundredths): void;
}
