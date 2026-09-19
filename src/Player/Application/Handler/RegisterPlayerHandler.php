<?php

namespace App\Player\Application\Handler;

use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;

final class RegisterPlayerHandler
{
    public function __construct(
        private readonly PlayerRepositoryInterface $players,
    ) {
    }

    public function __invoke(RegisterPlayerCommand $command): void
    {
        $id = PlayerId::fromString($command->playerId);
        $tenantId = TenantId::fromString($command->tenantId);
        $email = PlayerEmail::fromString($command->email);
        $player = Player::register($id, $tenantId, $email);
        $this->players->save($player);
    }
}
