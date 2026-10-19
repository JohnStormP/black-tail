<?php

namespace App\Tests\Risk\Doubles;

use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Domain\Service\RiskPlayerMetricsInterface;

final class InMemoryRiskPlayerMetrics implements RiskPlayerMetricsInterface
{
    public function __construct(
        private int $depositTotal = 0,
        private int $bettingTotal = 0,
        private int $betsCount = 0,
    ) {
    }

    public function sumDepositMinorSince(PlayerId $playerId, \DateTimeImmutable $since): int
    {
        return $this->depositTotal;
    }

    public function sumBettingMinorSince(PlayerId $playerId, \DateTimeImmutable $since): int
    {
        return $this->bettingTotal;
    }

    public function countBetsSince(PlayerId $playerId, \DateTimeImmutable $since): int
    {
        return $this->betsCount;
    }

    public function setDepositTotal(int $amount): void
    {
        $this->depositTotal = $amount;
    }

    public function setBettingTotal(int $amount): void
    {
        $this->bettingTotal = $amount;
    }

    public function setBetsCount(int $count): void
    {
        $this->betsCount = $count;
    }
}
