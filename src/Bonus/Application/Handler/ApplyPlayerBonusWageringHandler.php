<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\ApplyPlayerBonusWageringCommand;
use App\Bonus\Domain\Event\PlayerBonusCompleted;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Wallet\Domain\ValueObject\Money;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ApplyPlayerBonusWageringHandler
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ApplyPlayerBonusWageringCommand $command): void
    {
        $id = PlayerBonusId::fromString($command->playerBonusId);
        $bonus = $this->playerBonuses->getById($id);
        $amount = Money::of($command->amountMinor, $bonus->grantedAmount()->currency());
        $bonus->applyWagering($amount);
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
