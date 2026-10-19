<?php

namespace App\Risk\Application\Service;

use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Domain\Event\RiskSignalRaised;
use App\Risk\Domain\Exception\RiskBlockedException;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\Service\RiskEngine;
use App\Risk\Domain\Service\RiskPlayerMetricsInterface;
use App\Risk\Domain\ValueObject\RiskEvaluationContext;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Autoconfigure(public: true)]
final class RiskGuard
{
    public function __construct(
        private readonly RiskEngine $engine,
        private readonly RiskRuleRepositoryInterface $rules,
        private readonly RiskPlayerMetricsInterface $metrics,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function assertAllowed(
        TenantId $tenantId,
        PlayerId $playerId,
        RiskOperation $operation,
        int $amountMinor,
        \DateTimeImmutable $now = new \DateTimeImmutable(),
    ): void {
        $dayStart = $now->setTime(0, 0);

        $dailyDepositTotal = 0;
        $dailyBettingTotal = 0;
        $betsInWindow = 0;

        if (RiskOperation::Deposit === $operation) {
            $dailyDepositTotal = $this->metrics->sumDepositMinorSince($playerId, $dayStart);
        }

        if (RiskOperation::Bet === $operation) {
            $dailyBettingTotal = $this->metrics->sumBettingMinorSince($playerId, $dayStart);
            $windowSeconds = $this->maxVelocityWindowSeconds($tenantId);
            if ($windowSeconds > 0) {
                $betsInWindow = $this->metrics->countBetsSince(
                    $playerId,
                    $now->modify('-'.$windowSeconds.' seconds'),
                );
            }
        }

        $decision = $this->engine->evaluate(RiskEvaluationContext::create(
            $tenantId,
            $operation,
            $amountMinor,
            dailyDepositTotalMinor: $dailyDepositTotal,
            dailyBettingTotalMinor: $dailyBettingTotal,
            betsInWindow: $betsInWindow,
        ));

        if ($decision->isBlocked()) {
            foreach ($decision->matchedRuleIds() as $ruleId) {
                $rule = $this->rules->getById($ruleId);
                if (RiskRuleAction::Block === $rule->action()) {
                    throw RiskBlockedException::byRule($rule->id(), $rule->name());
                }
            }

            throw RiskBlockedException::byRule($decision->matchedRuleIds()[0], 'matched rule');
        }

        $action = $decision->action();
        if (null === $action) {
            return;
        }

        if (RiskRuleAction::Flag === $action || RiskRuleAction::ManualReview === $action) {
            $this->eventDispatcher->dispatch(new RiskSignalRaised(
                $tenantId,
                $playerId,
                $operation,
                $action,
                $decision->matchedRuleIds(),
                $now,
            ));
        }
    }

    private function maxVelocityWindowSeconds(TenantId $tenantId): int
    {
        $max = 0;
        foreach ($this->rules->findActiveByTenant($tenantId) as $rule) {
            if (RiskRuleType::BetVelocity !== $rule->type()) {
                continue;
            }

            $window = $rule->windowSeconds() ?? 0;
            if ($window > $max) {
                $max = $window;
            }
        }

        return $max;
    }
}
