<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\EnableTenantGameCommand;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\TenantGameId;

final class EnableTenantGameHandler
{
    public function __construct(
        private readonly TenantGameRepositoryInterface $tenantGames,
    ) {
    }

    public function __invoke(EnableTenantGameCommand $command): void
    {
        $tenantGameId = TenantGameId::fromString($command->tenantGameId);
        $link = $this->tenantGames->getById($tenantGameId);
        $link->enable();
        $this->tenantGames->save($link);
    }
}
