<?php

namespace App\Player\Domain\Repository;

use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\ValueObject\PlayerId;

interface PlayerRepositoryInterface
{
    public function save(Player $player): void;

    public function findById(PlayerId $id): ?Player;

    /**
     * @throws PlayerNotFoundException
     */
    public function getById(PlayerId $id): Player;
}
