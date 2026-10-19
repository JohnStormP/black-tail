<?php

namespace App\Tests\Risk\Application\Service;

use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Application\Service\RiskGuard;
use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Event\RiskSignalRaised;
use App\Risk\Domain\Exception\RiskBlockedException;
use App\Risk\Domain\Service\RiskEngine;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tests\Risk\Doubles\InMemoryRiskPlayerMetrics;
use App\Tests\Risk\Doubles\InMemoryRiskRuleRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RiskGuardTest extends TestCase
{
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string RULE_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private InMemoryRiskRuleRepository $rules;
    private InMemoryRiskPlayerMetrics $metrics;
    private EventDispatcher $dispatcher;
    private RiskGuard $guard;

    /** @var list<RiskSignalRaised> */
    private array $signals = [];

    protected function setUp(): void
    {
        $this->rules = new InMemoryRiskRuleRepository();
        $this->metrics = new InMemoryRiskPlayerMetrics();
        $this->dispatcher = new EventDispatcher();
        $this->signals = [];
        $this->dispatcher->addListener(RiskSignalRaised::class, function (RiskSignalRaised $event): void {
            $this->signals[] = $event;
        });

        $this->guard = new RiskGuard(
            new RiskEngine($this->rules),
            $this->rules,
            $this->metrics,
            $this->dispatcher,
        );
    }

    public function testAllowsWhenNoRules(): void
    {
        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Bet,
            10_000,
        );

        $this->assertSame([], $this->signals);
    }

    public function testBlocksDepositOverDailyLimit(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Daily deposit',
            RiskRuleType::DailyDepositLimit,
            10_000,
            RiskRuleAction::Block,
        );
        $rule->activate();
        $this->rules->save($rule);
        $this->metrics->setDepositTotal(9_000);

        $this->expectException(RiskBlockedException::class);

        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Deposit,
            2_000,
        );
    }

    public function testBlocksBetOverMaxBet(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Max bet',
            RiskRuleType::MaxBet,
            5_000,
            RiskRuleAction::Block,
        );
        $rule->activate();
        $this->rules->save($rule);

        $this->expectException(RiskBlockedException::class);

        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Bet,
            5_001,
        );
    }

    public function testBlocksWithdrawalOverMax(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Max withdrawal',
            RiskRuleType::MaxWithdrawal,
            50_000,
            RiskRuleAction::Block,
        );
        $rule->activate();
        $this->rules->save($rule);

        $this->expectException(RiskBlockedException::class);

        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Withdrawal,
            50_001,
        );
    }

    public function testBlocksBetVelocityUsingMetricsCount(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Velocity',
            RiskRuleType::BetVelocity,
            10,
            RiskRuleAction::Block,
            windowSeconds: 60,
        );
        $rule->activate();
        $this->rules->save($rule);
        $this->metrics->setBetsCount(10);

        $this->expectException(RiskBlockedException::class);

        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Bet,
            100,
        );
    }

    public function testFlagDispatchesSignalAndAllows(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Flag max bet',
            RiskRuleType::MaxBet,
            100,
            RiskRuleAction::Flag,
        );
        $rule->activate();
        $this->rules->save($rule);

        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Bet,
            200,
        );

        $this->assertCount(1, $this->signals);
        $this->assertSame(RiskRuleAction::Flag, $this->signals[0]->action());
        $this->assertSame(RiskOperation::Bet, $this->signals[0]->operation());
    }

    public function testManualReviewDispatchesSignalAndAllows(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Review withdrawal',
            RiskRuleType::MaxWithdrawal,
            1_000,
            RiskRuleAction::ManualReview,
        );
        $rule->activate();
        $this->rules->save($rule);

        $this->guard->assertAllowed(
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            RiskOperation::Withdrawal,
            2_000,
        );

        $this->assertCount(1, $this->signals);
        $this->assertSame(RiskRuleAction::ManualReview, $this->signals[0]->action());
    }
}
