<?php

namespace App\Player\Application\Handler;

use App\Player\Application\Command\SuspendPlayerCommand;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;

final class SuspendPlayerHandler
{
    public function __construct(
        private readonly PlayerRepositoryInterface $players,
    ) {
    }

    public function __invoke(SuspendPlayerCommand $command): void
    {
        $playerId = PlayerId::fromString($command->playerId);
        $player = $this->players->getById($playerId);
        $player->suspend();
        $this->players->save($player);
    }
}
