<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\AssignGameToTenantCommand;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class AssignGameToTenantHandler
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly GameRepositoryInterface $games,
        private readonly TenantGameRepositoryInterface $tenantGames,
    ) {
    }

    public function __invoke(AssignGameToTenantCommand $command): void
    {
        $tenantGameId = TenantGameId::fromString($command->tenantGameId);
        $tenantId = TenantId::fromString($command->tenantId);
        $gameId = GameId::fromString($command->gameId);
        $this->tenants->getById($tenantId);
        $this->games->getById($gameId);
        $tenantGame = $this->tenantGames->findByTenantAndGame($tenantId, $gameId);

        if (null !== $tenantGame) {
            return;
        }

        $link = TenantGame::assign($tenantGameId, $tenantId, $gameId);
        try {
            $this->tenantGames->save($link);
        } catch (UniqueConstraintViolationException) {
            return;
        }
    }
}
