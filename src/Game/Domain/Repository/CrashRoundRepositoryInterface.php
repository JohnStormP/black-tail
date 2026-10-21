<?php

namespace App\Game\Domain\Repository;

use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\ValueObject\CrashRoundId;

interface CrashRoundRepositoryInterface
{
    public function save(CrashRound $round): void;

    public function findById(CrashRoundId $id): ?CrashRound;

    public function getById(CrashRoundId $id): CrashRound;

    public function getByIdForUpdate(CrashRoundId $id): CrashRound;
}
