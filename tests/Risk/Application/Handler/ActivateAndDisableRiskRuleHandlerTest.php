<?php

namespace App\Tests\Risk\Application\Handler;

use App\Risk\Application\Command\ActivateRiskRuleCommand;
use App\Risk\Application\Command\CreateRiskRuleCommand;
use App\Risk\Application\Command\DisableRiskRuleCommand;
use App\Risk\Application\Handler\ActivateRiskRuleHandler;
use App\Risk\Application\Handler\CreateRiskRuleHandler;
use App\Risk\Application\Handler\DisableRiskRuleHandler;
use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\InvalidRiskRuleTransitionException;
use App\Risk\Domain\Exception\RiskRuleNotFoundException;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActivateAndDisableRiskRuleHandlerTest extends KernelTestCase
{
    private const string RULE_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private CreateRiskRuleHandler $create;
    private ActivateRiskRuleHandler $activate;
    private DisableRiskRuleHandler $disable;
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

        $this->create = new CreateRiskRuleHandler($this->rules, $tenants);
        $this->activate = new ActivateRiskRuleHandler($this->rules);
        $this->disable = new DisableRiskRuleHandler($this->rules);
    }

    public function testActivateFromDraft(): void
    {
        $this->createRule();

        ($this->activate)(new ActivateRiskRuleCommand(self::RULE_ID));

        $rule = $this->rules->getById(RiskRuleId::fromString(self::RULE_ID));
        $this->assertSame(RiskRuleStatus::Active, $rule->status());
        $this->assertTrue($rule->isActive());
    }

    public function testDisableFromActive(): void
    {
        $this->createRule();
        ($this->activate)(new ActivateRiskRuleCommand(self::RULE_ID));

        ($this->disable)(new DisableRiskRuleCommand(self::RULE_ID));

        $rule = $this->rules->getById(RiskRuleId::fromString(self::RULE_ID));
        $this->assertSame(RiskRuleStatus::Disabled, $rule->status());
        $this->assertFalse($rule->isActive());
    }

    public function testDisableFromDraft(): void
    {
        $this->createRule();

        ($this->disable)(new DisableRiskRuleCommand(self::RULE_ID));

        $this->assertSame(
            RiskRuleStatus::Disabled,
            $this->rules->getById(RiskRuleId::fromString(self::RULE_ID))->status(),
        );
    }

    public function testRejectsActivateWhenAlreadyActive(): void
    {
        $this->createRule();
        ($this->activate)(new ActivateRiskRuleCommand(self::RULE_ID));

        $this->expectException(InvalidRiskRuleTransitionException::class);

        ($this->activate)(new ActivateRiskRuleCommand(self::RULE_ID));
    }

    public function testRejectsUnknownRule(): void
    {
        $this->expectException(RiskRuleNotFoundException::class);

        ($this->activate)(new ActivateRiskRuleCommand('01CXYZ00000000000000000001'));
    }

    private function createRule(): void
    {
        ($this->create)(new CreateRiskRuleCommand(
            self::RULE_ID,
            self::TENANT_ID,
            'Max bet USD',
            RiskRuleType::MaxBet->value,
            10_000,
            RiskRuleAction::Block->value,
        ));
    }
}
