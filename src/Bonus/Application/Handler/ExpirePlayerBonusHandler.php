<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\ExpirePlayerBonusCommand;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;

final class ExpirePlayerBonusHandler
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
    ) {
    }

    public function __invoke(ExpirePlayerBonusCommand $command): void
    {
        $id = PlayerBonusId::fromString($command->playerBonusId);
        $bonus = $this->playerBonuses->getById($id);
        $now = null !== $command->now ? new \DateTimeImmutable($command->now) : new \DateTimeImmutable();
        $bonus->expire($now);
        $this->playerBonuses->save($bonus);
    }
}
