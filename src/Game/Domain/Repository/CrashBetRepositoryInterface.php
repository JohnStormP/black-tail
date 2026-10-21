<?php

namespace App\Game\Domain\Repository;

use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashRoundId;

interface CrashBetRepositoryInterface
{
    public function save(CrashBet $bet): void;

    public function findById(CrashBetId $id): ?CrashBet;

    public function getById(CrashBetId $id): CrashBet;

    /**
     * @return list<CrashBet>
     */
    public function findOpenByRoundId(CrashRoundId $roundId): array;
}
