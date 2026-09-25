<?php

namespace App\Game\Domain\Repository;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;

interface GameRepositoryInterface
{
    public function save(Game $game): void;

    public function findById(GameId $id): ?Game;

    public function getById(GameId $id): Game;

    public function findByCode(GameCode $code): ?Game;
}
