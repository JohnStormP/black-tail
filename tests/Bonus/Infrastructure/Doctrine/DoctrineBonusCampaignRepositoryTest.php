<?php

namespace App\Tests\Bonus\Infrastructure\Doctrine;

use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Exception\BonusCampaignNotFoundException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\BonusCampaignStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineBonusCampaignRepositoryTest extends KernelTestCase
{
    private const string CAMPAIGN_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private BonusCampaignRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(BonusCampaignRepositoryInterface::class);
        self::assertInstanceOf(BonusCampaignRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.BonusCampaign::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $id = BonusCampaignId::fromString(self::CAMPAIGN_ID);
        $this->repository->save($this->newCampaign($id));

        $found = $this->repository->findById($id);
        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($id));
        $this->assertTrue($found->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame('Welcome bonus', $found->name());
        $this->assertSame(1000, $found->bonusAmount()->amount());
        $this->assertSame(Currency::Usd, $found->bonusAmount()->currency());
        $this->assertSame(5, $found->wageringMultiplier());
        $this->assertSame(BonusCampaignStatus::Draft, $found->status());

        $loaded = $this->repository->getById($id);
        $this->assertTrue($loaded->id()->equals($id));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(BonusCampaignNotFoundException::class);

        $this->repository->getById(BonusCampaignId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->findById(
            BonusCampaignId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
        ));
    }

    private function newCampaign(BonusCampaignId $id): BonusCampaign
    {
        return BonusCampaign::create(
            $id,
            TenantId::fromString(self::TENANT_ID),
            'Welcome bonus',
            Money::of(1000, Currency::Usd),
            5,
        );
    }
}
