<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\StartCrashRoundCommand;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Shared\Application\TransactionManagerInterface;

final class StartCrashRoundHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(StartCrashRoundCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $round = $this->rounds->getByIdForUpdate(CrashRoundId::fromString($command->roundId));
            $round->start();
            $this->rounds->save($round);
        });
    }
}
