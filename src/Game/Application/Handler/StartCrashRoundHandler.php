<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\StartCrashRoundCommand;
use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Shared\Application\TransactionManagerInterface;

final class StartCrashRoundHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly TransactionManagerInterface $transactions,
        private readonly CrashRealtimePublisherInterface $realtime,
    ) {
    }

    public function __invoke(StartCrashRoundCommand $command): void
    {
        $roundId = CrashRoundId::fromString($command->roundId);

        $this->transactions->transactional(function () use ($roundId): void {
            $round = $this->rounds->getByIdForUpdate($roundId);
            $round->start();
            $this->rounds->save($round);
        });

        $this->realtime->publishStarted($roundId);
    }
}
