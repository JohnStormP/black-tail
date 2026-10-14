<?php

namespace App\Tests\Bonus\Application\Handler;

use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Application\Handler\CreateBonusCampaignHandler;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Exception\InvalidBonusCampaignException;
use App\Bonus\Domain\Exception\InvalidBonusCampaignIdException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\BonusCampaignStatus;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateBonusCampaignHandlerTest extends KernelTestCase
{
    private const string CAMPAIGN_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private CreateBonusCampaignHandler $handler;
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

        $this->handler = new CreateBonusCampaignHandler($this->campaigns, $tenants);
    }

    public function testCreatesDraftCampaign(): void
    {
        ($this->handler)($this->command());

        $campaign = $this->campaigns->getById(BonusCampaignId::fromString(self::CAMPAIGN_ID));
        $this->assertSame('Welcome bonus', $campaign->name());
        $this->assertTrue($campaign->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame(1000, $campaign->bonusAmount()->amount());
        $this->assertSame(Currency::Usd, $campaign->bonusAmount()->currency());
        $this->assertSame(5, $campaign->wageringMultiplier());
        $this->assertSame(5000, $campaign->requiredWager()->amount());
        $this->assertSame(BonusCampaignStatus::Draft, $campaign->status());
        $this->assertFalse($campaign->isActive());
    }

    public function testRejectsUnknownTenant(): void
    {
        $this->expectException(TenantNotFoundException::class);

        ($this->handler)($this->command(tenantId: '01CXYZ00000000000000000001'));
    }

    public function testRejectsInvalidCampaignId(): void
    {
        $this->expectException(InvalidBonusCampaignIdException::class);

        ($this->handler)($this->command(campaignId: 'not-a-ulid'));
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidBonusCampaignException::class);

        ($this->handler)($this->command(name: '   '));
    }

    public function testRejectsZeroBonusAmount(): void
    {
        $this->expectException(InvalidBonusCampaignException::class);

        ($this->handler)($this->command(bonusAmountMinor: 0));
    }

    public function testRejectsInvalidWageringMultiplier(): void
    {
        $this->expectException(InvalidBonusCampaignException::class);

        ($this->handler)($this->command(wageringMultiplier: 0));
    }

    private function command(
        ?string $campaignId = null,
        ?string $tenantId = null,
        ?string $name = null,
        ?int $bonusAmountMinor = null,
        ?int $wageringMultiplier = null,
    ): CreateBonusCampaignCommand {
        return new CreateBonusCampaignCommand(
            $campaignId ?? self::CAMPAIGN_ID,
            $tenantId ?? self::TENANT_ID,
            $name ?? 'Welcome bonus',
            $bonusAmountMinor ?? 1000,
            'USD',
            $wageringMultiplier ?? 5,
        );
    }
}
