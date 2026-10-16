<?php

namespace App\Bonus\Infrastructure\EventListener;

use App\Betting\Domain\Event\BetAccepted;
use App\Bonus\Domain\Event\PlayerBonusCompleted;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
final class ApplyWageringOnBetAcceptedListener
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(BetAccepted $event): void
    {
        $bonus = $this->playerBonuses->findOldestActiveByPlayer($event->playerId(), $event->amount()->currency());

        if (null === $bonus) {
            return;
        }

        $bonus->applyWagering($event->amount());
        $this->playerBonuses->save($bonus);

        if (PlayerBonusStatus::Completed === $bonus->status()) {
            $this->eventDispatcher->dispatch(new PlayerBonusCompleted(
                $bonus->id(),
                $bonus->playerId(),
                $bonus->campaignId(),
                $bonus->grantedAmount(),
            ));
        }
    }
}
