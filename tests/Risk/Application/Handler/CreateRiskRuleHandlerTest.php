<?php

namespace App\Tests\Risk\Application\Handler;

use App\Risk\Application\Command\CreateRiskRuleCommand;
use App\Risk\Application\Handler\CreateRiskRuleHandler;
use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\InvalidRiskRuleException;
use App\Risk\Domain\Exception\InvalidRiskRuleIdException;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateRiskRuleHandlerTest extends KernelTestCase
{
    private const string RULE_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private CreateRiskRuleHandler $handler;
    private RiskRuleRepositoryInterface $rules;

    protected function setUp(): void
    {
        self::bootKernel();

        $rules = self::getContainer()->get(RiskRuleRepositoryInterface::class);
        self::assertInstanceOf(RiskRuleRepositoryInterface::class, $rules);
        $this->rules = $rules;

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.RiskRule::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(
            TenantId::fromString(self::TENANT_ID),
            'Casino Alpha',
        ));

        $this->handler = new CreateRiskRuleHandler($this->rules, $tenants);
    }

    public function testCreatesDraftMaxBetRule(): void
    {
        ($this->handler)($this->command());

        $rule = $this->rules->getById(RiskRuleId::fromString(self::RULE_ID));
        $this->assertSame('Max bet USD', $rule->name());
        $this->assertTrue($rule->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame(RiskRuleType::MaxBet, $rule->type());
        $this->assertSame(10_000, $rule->threshold());
        $this->assertSame(RiskRuleAction::Block, $rule->action());
        $this->assertNull($rule->windowSeconds());
        $this->assertSame(RiskRuleStatus::Draft, $rule->status());
        $this->assertFalse($rule->isActive());
    }

    public function testCreatesBetVelocityWithWindow(): void
    {
        ($this->handler)($this->command(
            type: RiskRuleType::BetVelocity->value,
            threshold: 10,
            action: RiskRuleAction::Flag->value,
            windowSeconds: 60,
            name: 'Velocity',
        ));

        $rule = $this->rules->getById(RiskRuleId::fromString(self::RULE_ID));
        $this->assertSame(RiskRuleType::BetVelocity, $rule->type());
        $this->assertSame(60, $rule->windowSeconds());
        $this->assertSame(RiskRuleAction::Flag, $rule->action());
    }

    public function testRejectsUnknownTenant(): void
    {
        $this->expectException(TenantNotFoundException::class);

        ($this->handler)($this->command(tenantId: '01CXYZ00000000000000000001'));
    }

    public function testRejectsInvalidRuleId(): void
    {
        $this->expectException(InvalidRiskRuleIdException::class);

        ($this->handler)($this->command(ruleId: 'not-a-ulid'));
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidRiskRuleException::class);

        ($this->handler)($this->command(name: '   '));
    }

    public function testRejectsVelocityWithoutWindow(): void
    {
        $this->expectException(InvalidRiskRuleException::class);

        ($this->handler)($this->command(
            type: RiskRuleType::BetVelocity->value,
            threshold: 10,
            action: RiskRuleAction::Flag->value,
            windowSeconds: null,
        ));
    }

    private function command(
        ?string $ruleId = null,
        ?string $tenantId = null,
        ?string $name = null,
        ?string $type = null,
        ?int $threshold = null,
        ?string $action = null,
        ?int $windowSeconds = null,
    ): CreateRiskRuleCommand {
        return new CreateRiskRuleCommand(
            $ruleId ?? self::RULE_ID,
            $tenantId ?? self::TENANT_ID,
            $name ?? 'Max bet USD',
            $type ?? RiskRuleType::MaxBet->value,
            $threshold ?? 10_000,
            $action ?? RiskRuleAction::Block->value,
            $windowSeconds,
        );
    }
}
