<?php

namespace App\Game\Domain\Repository;

use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Exception\GameRoundNotFoundException;
use App\Game\Domain\ValueObject\GameRoundId;

interface GameRoundRepositoryInterface
{
    public function save(GameRound $round): void;

    public function findById(GameRoundId $id): ?GameRound;

    /**
     * @throws GameRoundNotFoundException
     */
    public function getById(GameRoundId $id): GameRound;
}
