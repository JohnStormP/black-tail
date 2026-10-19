<?php

namespace App\Risk\Domain\ValueObject;

use App\Risk\Domain\Exception\InvalidRiskEvaluationException;
use App\Tenant\Domain\ValueObject\TenantId;

final readonly class RiskEvaluationContext
{
    private function __construct(
        private TenantId $tenantId,
        private RiskOperation $operation,
        private int $amountMinor,
        private int $dailyDepositTotalMinor,
        private int $dailyBettingTotalMinor,
        private int $betsInWindow,
    ) {
    }

    public static function create(
        TenantId $tenantId,
        RiskOperation $operation,
        int $amountMinor,
        int $dailyDepositTotalMinor = 0,
        int $dailyBettingTotalMinor = 0,
        int $betsInWindow = 0,
    ): self {
        if ($amountMinor < 0) {
            throw InvalidRiskEvaluationException::negativeAmount();
        }

        if ($dailyDepositTotalMinor < 0) {
            throw InvalidRiskEvaluationException::negativeDailyDepositTotal();
        }

        if ($dailyBettingTotalMinor < 0) {
            throw InvalidRiskEvaluationException::negativeDailyBettingTotal();
        }

        if ($betsInWindow < 0) {
            throw InvalidRiskEvaluationException::negativeBetsInWindow();
        }

        return new self(
            $tenantId,
            $operation,
            $amountMinor,
            $dailyDepositTotalMinor,
            $dailyBettingTotalMinor,
            $betsInWindow,
        );
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function operation(): RiskOperation
    {
        return $this->operation;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function dailyDepositTotalMinor(): int
    {
        return $this->dailyDepositTotalMinor;
    }

    public function dailyBettingTotalMinor(): int
    {
        return $this->dailyBettingTotalMinor;
    }

    public function betsInWindow(): int
    {
        return $this->betsInWindow;
    }
}
