<?php

namespace App\Tests\Risk\Infrastructure\Doctrine;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\RiskRuleNotFoundException;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRiskRuleRepositoryTest extends KernelTestCase
{
    private const string RULE_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string RULE_ID_2 = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string TENANT_ID = '01CXYZ00000000000000000001';
    private const string OTHER_TENANT_ID = '01DXYZ00000000000000000002';

    private RiskRuleRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(RiskRuleRepositoryInterface::class);
        self::assertInstanceOf(RiskRuleRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.RiskRule::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $id = RiskRuleId::fromString(self::RULE_ID);
        $this->repository->save($this->newRule($id));

        $found = $this->repository->findById($id);
        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($id));
        $this->assertTrue($found->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame('Max bet USD', $found->name());
        $this->assertSame(RiskRuleType::MaxBet, $found->type());
        $this->assertSame(10_000, $found->threshold());
        $this->assertNull($found->windowSeconds());
        $this->assertSame(RiskRuleAction::Block, $found->action());
        $this->assertSame(RiskRuleStatus::Draft, $found->status());

        $loaded = $this->repository->getById($id);
        $this->assertTrue($loaded->id()->equals($id));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(RiskRuleNotFoundException::class);

        $this->repository->getById(RiskRuleId::fromString(self::RULE_ID_2));
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->findById(
            RiskRuleId::fromString(self::RULE_ID_2),
        ));
    }

    public function testFindActiveByTenantReturnsOnlyActiveForTenantOrderedByCreatedAt(): void
    {
        $older = $this->newRule(
            RiskRuleId::fromString(self::RULE_ID),
            createdAt: new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $older->activate();

        $newer = $this->newRule(
            RiskRuleId::fromString(self::RULE_ID_2),
            name: 'Velocity',
            type: RiskRuleType::BetVelocity,
            threshold: 10,
            action: RiskRuleAction::Flag,
            windowSeconds: 60,
            createdAt: new \DateTimeImmutable('2026-01-02T10:00:00+00:00'),
        );
        $newer->activate();

        $draft = $this->newRule(
            RiskRuleId::fromString('01EXYZ00000000000000000003'),
            name: 'Draft max bet',
        );

        $otherTenant = $this->newRule(
            RiskRuleId::fromString('01FXYZ00000000000000000004'),
            tenantId: TenantId::fromString(self::OTHER_TENANT_ID),
            name: 'Other tenant',
        );
        $otherTenant->activate();

        $this->repository->save($older);
        $this->repository->save($newer);
        $this->repository->save($draft);
        $this->repository->save($otherTenant);

        $active = $this->repository->findActiveByTenant(TenantId::fromString(self::TENANT_ID));

        $this->assertCount(2, $active);
        $this->assertTrue($active[0]->id()->equals(RiskRuleId::fromString(self::RULE_ID)));
        $this->assertTrue($active[1]->id()->equals(RiskRuleId::fromString(self::RULE_ID_2)));
        $this->assertSame(RiskRuleStatus::Active, $active[0]->status());
        $this->assertSame(RiskRuleStatus::Active, $active[1]->status());
    }

    private function newRule(
        RiskRuleId $id,
        ?TenantId $tenantId = null,
        string $name = 'Max bet USD',
        RiskRuleType $type = RiskRuleType::MaxBet,
        int $threshold = 10_000,
        RiskRuleAction $action = RiskRuleAction::Block,
        ?int $windowSeconds = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): RiskRule {
        return RiskRule::create(
            $id,
            $tenantId ?? TenantId::fromString(self::TENANT_ID),
            $name,
            $type,
            $threshold,
            $action,
            $windowSeconds,
            $createdAt,
        );
    }
}
