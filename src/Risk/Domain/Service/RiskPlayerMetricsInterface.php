<?php

namespace App\Risk\Domain\Service;

use App\Player\Domain\ValueObject\PlayerId;

interface RiskPlayerMetricsInterface
{
    public function sumDepositMinorSince(PlayerId $playerId, \DateTimeImmutable $since): int;

    public function sumBettingMinorSince(PlayerId $playerId, \DateTimeImmutable $since): int;

    public function countBetsSince(PlayerId $playerId, \DateTimeImmutable $since): int;
}
