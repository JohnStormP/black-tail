<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\CancelBetCommand;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetStatus;

final class CancelBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
    ) {
    }

    public function __invoke(CancelBetCommand $command): void
    {
        $betId = BetId::fromString($command->betId);
        $bet = $this->bets->getById($betId);
        if (BetStatus::Cancelled === $bet->status()) {
            return;
        }
        $bet->cancel();
        $this->bets->save($bet);
    }
}
