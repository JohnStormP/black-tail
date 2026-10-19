<?php

namespace App\Tests\Risk\Domain\Entity;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\InvalidRiskRuleException;
use App\Risk\Domain\Exception\InvalidRiskRuleTransitionException;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class RiskRuleTest extends TestCase
{
    private const string RULE_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    public function testCreateStartsAsDraft(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $rule = $this->createMaxBetRule(10_000, $createdAt);

        $this->assertTrue($rule->id()->equals(RiskRuleId::fromString(self::RULE_ID)));
        $this->assertTrue($rule->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame('Max bet USD', $rule->name());
        $this->assertSame(RiskRuleType::MaxBet, $rule->type());
        $this->assertSame(10_000, $rule->threshold());
        $this->assertNull($rule->windowSeconds());
        $this->assertSame(RiskRuleAction::Block, $rule->action());
        $this->assertSame(RiskRuleStatus::Draft, $rule->status());
        $this->assertFalse($rule->isActive());
        $this->assertEquals($createdAt, $rule->createdAt());
    }

    public function testCreateVelocityRuleRequiresWindow(): void
    {
        $this->expectException(InvalidRiskRuleException::class);

        RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            'Velocity',
            RiskRuleType::BetVelocity,
            10,
            RiskRuleAction::Flag,
            null,
        );
    }

    public function testCreateVelocityRuleWithWindow(): void
    {
        $rule = RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            '10 bets in 2 seconds',
            RiskRuleType::BetVelocity,
            10,
            RiskRuleAction::Flag,
            2,
        );

        $this->assertSame(RiskRuleType::BetVelocity, $rule->type());
        $this->assertSame(10, $rule->threshold());
        $this->assertSame(2, $rule->windowSeconds());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(InvalidRiskRuleException::class);

        $this->createMaxBetRule(10_000, name: '   ');
    }

    public function testCreateRejectsNonPositiveThreshold(): void
    {
        $this->expectException(InvalidRiskRuleException::class);

        $this->createMaxBetRule(0);
    }

    public function testActivateFromDraft(): void
    {
        $rule = $this->createMaxBetRule(10_000);
        $rule->activate();

        $this->assertSame(RiskRuleStatus::Active, $rule->status());
        $this->assertTrue($rule->isActive());
    }

    public function testDisableFromActive(): void
    {
        $rule = $this->createMaxBetRule(10_000);
        $rule->activate();
        $rule->disable();

        $this->assertSame(RiskRuleStatus::Disabled, $rule->status());
        $this->assertFalse($rule->isActive());
    }

    public function testDisableFromDraft(): void
    {
        $rule = $this->createMaxBetRule(10_000);
        $rule->disable();

        $this->assertSame(RiskRuleStatus::Disabled, $rule->status());
    }

    public function testActivateRejectsFromActive(): void
    {
        $rule = $this->createMaxBetRule(10_000);
        $rule->activate();

        $this->expectException(InvalidRiskRuleTransitionException::class);
        $rule->activate();
    }

    public function testDisableRejectsFromDisabled(): void
    {
        $rule = $this->createMaxBetRule(10_000);
        $rule->disable();

        $this->expectException(InvalidRiskRuleTransitionException::class);
        $rule->disable();
    }

    private function createMaxBetRule(
        int $threshold,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00'),
        string $name = 'Max bet USD',
    ): RiskRule {
        return RiskRule::create(
            RiskRuleId::fromString(self::RULE_ID),
            TenantId::fromString(self::TENANT_ID),
            $name,
            RiskRuleType::MaxBet,
            $threshold,
            RiskRuleAction::Block,
            null,
            $createdAt,
        );
    }
}
