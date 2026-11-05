<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\CancelBetCommand;
use App\Betting\Domain\Event\BetCancelled;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Shared\Application\TransactionManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CancelBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly TransactionManagerInterface $transactions,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CancelBetCommand $command): void
    {
        $event = null;

        $this->transactions->transactional(function () use ($command, &$event): void {
            $betId = BetId::fromString($command->betId);
            $bet = $this->bets->getByIdForUpdate($betId);

            if (BetStatus::Cancelled === $bet->status()) {
                return;
            }

            $bet->cancel();
            $this->bets->save($bet);

            $event = new BetCancelled($betId, $bet->playerId(), $bet->amount());
        });

        if (null !== $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
