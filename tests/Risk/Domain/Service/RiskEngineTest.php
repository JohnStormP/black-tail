<?php

namespace App\Tests\Risk\Domain\Service;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\InvalidRiskEvaluationException;
use App\Risk\Domain\Service\RiskEngine;
use App\Risk\Domain\ValueObject\RiskEvaluationContext;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tests\Risk\Doubles\InMemoryRiskRuleRepository;
use PHPUnit\Framework\TestCase;

final class RiskEngineTest extends TestCase
{
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string OTHER_TENANT_ID = '01CXYZ00000000000000000001';
    private const string RULE_MAX_BET = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string RULE_FLAG = '01DXYZ00000000000000000002';
    private const string RULE_REVIEW = '01EXYZ00000000000000000003';
    private const string RULE_DEPOSIT = '01FXYZ00000000000000000004';
    private const string RULE_VELOCITY = '01GXYZ00000000000000000005';

    private InMemoryRiskRuleRepository $rules;
    private RiskEngine $engine;

    protected function setUp(): void
    {
        $this->rules = new InMemoryRiskRuleRepository();
        $this->engine = new RiskEngine($this->rules);
    }

    public function testAllowWhenNoActiveRules(): void
    {
        $decision = $this->engine->evaluate($this->betContext(amountMinor: 5_000));

        $this->assertTrue($decision->isAllowed());
        $this->assertFalse($decision->isBlocked());
        $this->assertNull($decision->action());
        $this->assertSame([], $decision->matchedRuleIds());
    }

    public function testIgnoresDraftAndOtherTenantRules(): void
    {
        $draft = $this->createRule(self::RULE_MAX_BET, RiskRuleType::MaxBet, 1_000, RiskRuleAction::Block);
        $this->rules->save($draft);

        $other = $this->createRule(
            self::RULE_FLAG,
            RiskRuleType::MaxBet,
            1_000,
            RiskRuleAction::Block,
            tenantId: TenantId::fromString(self::OTHER_TENANT_ID),
        );
        $other->activate();
        $this->rules->save($other);

        $decision = $this->engine->evaluate($this->betContext(amountMinor: 5_000));

        $this->assertTrue($decision->isAllowed());
        $this->assertSame([], $decision->matchedRuleIds());
    }

    public function testMaxBetBlockWhenAmountExceedsThreshold(): void
    {
        $rule = $this->createRule(self::RULE_MAX_BET, RiskRuleType::MaxBet, 10_000, RiskRuleAction::Block);
        $rule->activate();
        $this->rules->save($rule);

        $blocked = $this->engine->evaluate($this->betContext(amountMinor: 10_001));
        $this->assertTrue($blocked->isBlocked());
        $this->assertSame(RiskRuleAction::Block, $blocked->action());
        $this->assertTrue($blocked->matchedRuleIds()[0]->equals(RiskRuleId::fromString(self::RULE_MAX_BET)));

        $allowed = $this->engine->evaluate($this->betContext(amountMinor: 10_000));
        $this->assertTrue($allowed->isAllowed());
        $this->assertSame([], $allowed->matchedRuleIds());
    }

    public function testMaxBetRuleDoesNotApplyToDeposit(): void
    {
        $rule = $this->createRule(self::RULE_MAX_BET, RiskRuleType::MaxBet, 100, RiskRuleAction::Block);
        $rule->activate();
        $this->rules->save($rule);

        $decision = $this->engine->evaluate(RiskEvaluationContext::create(
            TenantId::fromString(self::TENANT_ID),
            RiskOperation::Deposit,
            50_000,
        ));

        $this->assertTrue($decision->isAllowed());
    }

    public function testDailyDepositLimitIncludesCurrentAmount(): void
    {
        $rule = $this->createRule(self::RULE_DEPOSIT, RiskRuleType::DailyDepositLimit, 10_000, RiskRuleAction::Block);
        $rule->activate();
        $this->rules->save($rule);

        $blocked = $this->engine->evaluate(RiskEvaluationContext::create(
            TenantId::fromString(self::TENANT_ID),
            RiskOperation::Deposit,
            amountMinor: 2_000,
            dailyDepositTotalMinor: 9_000,
        ));
        $this->assertTrue($blocked->isBlocked());

        $allowed = $this->engine->evaluate(RiskEvaluationContext::create(
            TenantId::fromString(self::TENANT_ID),
            RiskOperation::Deposit,
            amountMinor: 1_000,
            dailyDepositTotalMinor: 9_000,
        ));
        $this->assertTrue($allowed->isAllowed());
    }

    public function testDailyBettingLimitIncludesCurrentAmount(): void
    {
        $rule = $this->createRule(self::RULE_MAX_BET, RiskRuleType::DailyBettingLimit, 5_000, RiskRuleAction::Block);
        $rule->activate();
        $this->rules->save($rule);

        $blocked = $this->engine->evaluate($this->betContext(
            amountMinor: 1_500,
            dailyBettingTotalMinor: 4_000,
        ));
        $this->assertTrue($blocked->isBlocked());
    }

    public function testMaxWithdrawalBlock(): void
    {
        $rule = $this->createRule(self::RULE_MAX_BET, RiskRuleType::MaxWithdrawal, 50_000, RiskRuleAction::Block);
        $rule->activate();
        $this->rules->save($rule);

        $blocked = $this->engine->evaluate(RiskEvaluationContext::create(
            TenantId::fromString(self::TENANT_ID),
            RiskOperation::Withdrawal,
            50_001,
        ));
        $this->assertTrue($blocked->isBlocked());
    }

    public function testBetVelocityUsesExistingCountInWindow(): void
    {
        $rule = $this->createRule(
            self::RULE_VELOCITY,
            RiskRuleType::BetVelocity,
            10,
            RiskRuleAction::Block,
            windowSeconds: 60,
        );
        $rule->activate();
        $this->rules->save($rule);

        $blocked = $this->engine->evaluate($this->betContext(amountMinor: 100, betsInWindow: 10));
        $this->assertTrue($blocked->isBlocked());

        $allowed = $this->engine->evaluate($this->betContext(amountMinor: 100, betsInWindow: 9));
        $this->assertTrue($allowed->isAllowed());
    }

    public function testStrongestActionWinsAmongMatches(): void
    {
        $flag = $this->createRule(self::RULE_FLAG, RiskRuleType::MaxBet, 100, RiskRuleAction::Flag);
        $flag->activate();
        $review = $this->createRule(self::RULE_REVIEW, RiskRuleType::MaxBet, 100, RiskRuleAction::ManualReview);
        $review->activate();
        $block = $this->createRule(self::RULE_MAX_BET, RiskRuleType::MaxBet, 100, RiskRuleAction::Block);
        $block->activate();

        $this->rules->save($flag);
        $this->rules->save($review);
        $this->rules->save($block);

        $decision = $this->engine->evaluate($this->betContext(amountMinor: 200));

        $this->assertTrue($decision->isBlocked());
        $this->assertSame(RiskRuleAction::Block, $decision->action());
        $this->assertCount(3, $decision->matchedRuleIds());
    }

    public function testFlagWhenOnlyFlagMatches(): void
    {
        $flag = $this->createRule(self::RULE_FLAG, RiskRuleType::MaxBet, 100, RiskRuleAction::Flag);
        $flag->activate();
        $this->rules->save($flag);

        $decision = $this->engine->evaluate($this->betContext(amountMinor: 200));

        $this->assertTrue($decision->isAllowed());
        $this->assertTrue($decision->isFlagged());
        $this->assertFalse($decision->isBlocked());
        $this->assertSame(RiskRuleAction::Flag, $decision->action());
    }

    public function testManualReviewWhenOnlyManualReviewMatches(): void
    {
        $review = $this->createRule(self::RULE_REVIEW, RiskRuleType::MaxBet, 100, RiskRuleAction::ManualReview);
        $review->activate();
        $this->rules->save($review);

        $decision = $this->engine->evaluate($this->betContext(amountMinor: 200));

        $this->assertTrue($decision->isAllowed());
        $this->assertTrue($decision->requiresManualReview());
        $this->assertSame(RiskRuleAction::ManualReview, $decision->action());
    }

    public function testContextRejectsNegativeAmount(): void
    {
        $this->expectException(InvalidRiskEvaluationException::class);

        RiskEvaluationContext::create(
            TenantId::fromString(self::TENANT_ID),
            RiskOperation::Bet,
            -1,
        );
    }

    private function betContext(
        int $amountMinor,
        int $dailyBettingTotalMinor = 0,
        int $betsInWindow = 0,
    ): RiskEvaluationContext {
        return RiskEvaluationContext::create(
            TenantId::fromString(self::TENANT_ID),
            RiskOperation::Bet,
            $amountMinor,
            dailyBettingTotalMinor: $dailyBettingTotalMinor,
            betsInWindow: $betsInWindow,
        );
    }

    private function createRule(
        string $id,
        RiskRuleType $type,
        int $threshold,
        RiskRuleAction $action,
        ?int $windowSeconds = null,
        ?TenantId $tenantId = null,
    ): RiskRule {
        return RiskRule::create(
            RiskRuleId::fromString($id),
            $tenantId ?? TenantId::fromString(self::TENANT_ID),
            $type->value,
            $type,
            $threshold,
            $action,
            $windowSeconds,
        );
    }
}
