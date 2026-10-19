<?php

namespace App\Risk\Domain\Service;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskDecision;
use App\Risk\Domain\ValueObject\RiskEvaluationContext;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleType;

final class RiskEngine
{
    public function __construct(
        private readonly RiskRuleRepositoryInterface $rules,
    ) {
    }

    public function evaluate(RiskEvaluationContext $context): RiskDecision
    {
        $matched = [];

        foreach ($this->rules->findActiveByTenant($context->tenantId()) as $rule) {
            if (!$this->appliesToOperation($rule, $context->operation())) {
                continue;
            }

            if (!$this->isTriggered($rule, $context)) {
                continue;
            }

            $matched[] = $rule;
        }

        if ([] === $matched) {
            return RiskDecision::allow();
        }

        $strongest = $matched[0]->action();
        foreach ($matched as $rule) {
            if ($this->severity($rule->action()) > $this->severity($strongest)) {
                $strongest = $rule->action();
            }
        }

        return RiskDecision::of(
            $strongest,
            array_map(
                static fn (RiskRule $rule): RiskRuleId => $rule->id(),
                $matched,
            ),
        );
    }

    private function appliesToOperation(RiskRule $rule, RiskOperation $operation): bool
    {
        return match ($rule->type()) {
            RiskRuleType::DailyDepositLimit => RiskOperation::Deposit === $operation,
            RiskRuleType::MaxWithdrawal => RiskOperation::Withdrawal === $operation,
            RiskRuleType::MaxBet,
            RiskRuleType::DailyBettingLimit,
            RiskRuleType::BetVelocity => RiskOperation::Bet === $operation,
        };
    }

    private function isTriggered(RiskRule $rule, RiskEvaluationContext $context): bool
    {
        $threshold = $rule->threshold();

        return match ($rule->type()) {
            RiskRuleType::MaxBet,
            RiskRuleType::MaxWithdrawal => $context->amountMinor() > $threshold,
            RiskRuleType::DailyDepositLimit => ($context->dailyDepositTotalMinor() + $context->amountMinor()) > $threshold,
            RiskRuleType::DailyBettingLimit => ($context->dailyBettingTotalMinor() + $context->amountMinor()) > $threshold,
            RiskRuleType::BetVelocity => $context->betsInWindow() >= $threshold,
        };
    }

    private function severity(RiskRuleAction $action): int
    {
        return match ($action) {
            RiskRuleAction::Block => 3,
            RiskRuleAction::ManualReview => 2,
            RiskRuleAction::Flag => 1,
        };
    }
}
