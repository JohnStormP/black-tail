<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\DisableTenantGameCommand;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\TenantGameId;

final class DisableTenantGameHandler
{
    public function __construct(
        private readonly TenantGameRepositoryInterface $tenantGames,
    ) {
    }

    public function __invoke(DisableTenantGameCommand $command): void
    {
        $tenantGameId = TenantGameId::fromString($command->tenantGameId);
        $link = $this->tenantGames->getById($tenantGameId);
        $link->disable();
        $this->tenantGames->save($link);
    }
}
