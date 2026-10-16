<?php

namespace App\Tests\Bonus\Application\Handler;

use App\Bonus\Application\Command\ActivateBonusCampaignCommand;
use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Application\Command\DisableBonusCampaignCommand;
use App\Bonus\Application\Handler\ActivateBonusCampaignHandler;
use App\Bonus\Application\Handler\CreateBonusCampaignHandler;
use App\Bonus\Application\Handler\DisableBonusCampaignHandler;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Exception\BonusCampaignNotFoundException;
use App\Bonus\Domain\Exception\InvalidBonusCampaignTransitionException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\BonusCampaignStatus;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActivateAndDisableBonusCampaignHandlerTest extends KernelTestCase
{
    private const string CAMPAIGN_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private CreateBonusCampaignHandler $create;
    private ActivateBonusCampaignHandler $activate;
    private DisableBonusCampaignHandler $disable;
    private BonusCampaignRepositoryInterface $campaigns;

    protected function setUp(): void
    {
        self::bootKernel();

        $campaigns = self::getContainer()->get(BonusCampaignRepositoryInterface::class);
        self::assertInstanceOf(BonusCampaignRepositoryInterface::class, $campaigns);
        $this->campaigns = $campaigns;

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.BonusCampaign::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(
            TenantId::fromString(self::TENANT_ID),
            'Casino Alpha',
        ));

        $this->create = new CreateBonusCampaignHandler($this->campaigns, $tenants);
        $this->activate = new ActivateBonusCampaignHandler($this->campaigns);
        $this->disable = new DisableBonusCampaignHandler($this->campaigns);
    }

    public function testActivateFromDraft(): void
    {
        $this->createCampaign();

        ($this->activate)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));

        $campaign = $this->campaigns->getById(BonusCampaignId::fromString(self::CAMPAIGN_ID));
        $this->assertSame(BonusCampaignStatus::Active, $campaign->status());
        $this->assertTrue($campaign->isActive());
    }

    public function testDisableFromActive(): void
    {
        $this->createCampaign();
        ($this->activate)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));

        ($this->disable)(new DisableBonusCampaignCommand(self::CAMPAIGN_ID));

        $campaign = $this->campaigns->getById(BonusCampaignId::fromString(self::CAMPAIGN_ID));
        $this->assertSame(BonusCampaignStatus::Disabled, $campaign->status());
        $this->assertFalse($campaign->isActive());
    }

    public function testDisableFromDraft(): void
    {
        $this->createCampaign();

        ($this->disable)(new DisableBonusCampaignCommand(self::CAMPAIGN_ID));

        $this->assertSame(
            BonusCampaignStatus::Disabled,
            $this->campaigns->getById(BonusCampaignId::fromString(self::CAMPAIGN_ID))->status(),
        );
    }

    public function testRejectsActivateWhenAlreadyActive(): void
    {
        $this->createCampaign();
        ($this->activate)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));

        $this->expectException(InvalidBonusCampaignTransitionException::class);

        ($this->activate)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));
    }

    public function testRejectsUnknownCampaign(): void
    {
        $this->expectException(BonusCampaignNotFoundException::class);

        ($this->activate)(new ActivateBonusCampaignCommand('01CXYZ00000000000000000001'));
    }

    private function createCampaign(): void
    {
        ($this->create)(new CreateBonusCampaignCommand(
            self::CAMPAIGN_ID,
            self::TENANT_ID,
            'Welcome bonus',
            1000,
            'USD',
            5,
        ));
    }
}
