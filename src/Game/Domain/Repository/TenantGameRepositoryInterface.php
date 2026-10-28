<?php

namespace App\Game\Domain\Repository;

use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\TenantGameNotFoundException;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\ValueObject\TenantId;

interface TenantGameRepositoryInterface
{
    public function save(TenantGame $tenantGame): void;

    public function findById(TenantGameId $id): ?TenantGame;

    /**
     * @throws TenantGameNotFoundException
     */
    public function getById(TenantGameId $id): TenantGame;

    public function findByTenantAndGame(TenantId $tenantId, GameId $gameId): ?TenantGame;

    /**
     * @return list<TenantGame>
     */
    public function findByTenantId(TenantId $tenantId): array;
}
