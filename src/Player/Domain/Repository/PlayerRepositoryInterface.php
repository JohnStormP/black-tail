<?php

namespace App\Player\Domain\Repository;

use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;

interface PlayerRepositoryInterface
{
    public function save(Player $player): void;

    public function findById(PlayerId $id): ?Player;

    /**
     * @throws PlayerNotFoundException
     */
    public function getById(PlayerId $id): Player;

    /**
     * @return list<Player>
     */
    public function findByTenantId(TenantId $tenantId): array;
}
