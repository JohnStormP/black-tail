<?php

namespace App\Bonus\Infrastructure\EventListener;

use App\Betting\Domain\Event\BetAccepted;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ApplyWageringOnBetAcceptedListener
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
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
    }
}
