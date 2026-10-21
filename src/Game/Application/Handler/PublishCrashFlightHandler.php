<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\PublishCrashFlightCommand;
use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Domain\ValueObject\CrashRoundStatus;

final class PublishCrashFlightHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly CrashRealtimePublisherInterface $realtime,
    ) {
    }

    public function __invoke(PublishCrashFlightCommand $command): void
    {
        $round = $this->rounds->getById(CrashRoundId::fromString($command->roundId));

        if (CrashRoundStatus::Running !== $round->status()) {
            return;
        }

        $crash = $round->crashPoint()->toHundredths();
        $step = max(1, $command->stepHundredths);

        for ($i = CrashMultiplier::MIN_HUNDREDTHS; $i < $crash; $i += $step) {
            $this->realtime->publishTick($round->id(), $i);
        }
    }
}
