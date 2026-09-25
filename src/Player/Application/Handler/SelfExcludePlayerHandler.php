<?php

namespace App\Player\Application\Handler;

use App\Player\Application\Command\SelfExcludePlayerCommand;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;

final class SelfExcludePlayerHandler
{
    public function __construct(
        private readonly PlayerRepositoryInterface $players,
    ) {
    }

    public function __invoke(SelfExcludePlayerCommand $command): void
    {
        $playerId = PlayerId::fromString($command->playerId);
        $player = $this->players->getById($playerId);
        $player->selfExclude();
        $this->players->save($player);
    }
}
