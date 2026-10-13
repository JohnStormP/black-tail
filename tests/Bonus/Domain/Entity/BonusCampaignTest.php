<?php

namespace App\Tests\Bonus\Domain\Entity;

use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Exception\InvalidBonusCampaignException;
use App\Bonus\Domain\Exception\InvalidBonusCampaignTransitionException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\BonusCampaignStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class BonusCampaignTest extends TestCase
{
    private const string CAMPAIGN_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    public function testCreateStartsAsDraft(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $campaign = $this->createCampaign(1000, 5, 'Welcome bonus', $createdAt);

        $this->assertTrue($campaign->id()->equals(BonusCampaignId::fromString(self::CAMPAIGN_ID)));
        $this->assertTrue($campaign->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame('Welcome bonus', $campaign->name());
        $this->assertSame(1000, $campaign->bonusAmount()->amount());
        $this->assertSame(Currency::Usd, $campaign->bonusAmount()->currency());
        $this->assertSame(5, $campaign->wageringMultiplier());
        $this->assertSame(5000, $campaign->requiredWager()->amount());
        $this->assertSame(BonusCampaignStatus::Draft, $campaign->status());
        $this->assertFalse($campaign->isActive());
        $this->assertEquals($createdAt, $campaign->createdAt());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(InvalidBonusCampaignException::class);

        $this->createCampaign(1000, 5, '   ');
    }

    public function testCreateRejectsZeroBonusAmount(): void
    {
        $this->expectException(InvalidBonusCampaignException::class);

        $this->createCampaign(0, 5);
    }

    public function testCreateRejectsWageringMultiplierBelowOne(): void
    {
        $this->expectException(InvalidBonusCampaignException::class);

        $this->createCampaign(1000, 0);
    }

    public function testActivateFromDraft(): void
    {
        $campaign = $this->createCampaign(1000, 5);
        $campaign->activate();

        $this->assertSame(BonusCampaignStatus::Active, $campaign->status());
        $this->assertTrue($campaign->isActive());
    }

    public function testDisableFromActive(): void
    {
        $campaign = $this->createCampaign(1000, 5);
        $campaign->activate();
        $campaign->disable();

        $this->assertSame(BonusCampaignStatus::Disabled, $campaign->status());
        $this->assertFalse($campaign->isActive());
    }

    public function testDisableFromDraft(): void
    {
        $campaign = $this->createCampaign(1000, 5);
        $campaign->disable();

        $this->assertSame(BonusCampaignStatus::Disabled, $campaign->status());
    }

    public function testRejectsActivateFromActive(): void
    {
        $campaign = $this->createCampaign(1000, 5);
        $campaign->activate();

        $this->expectException(InvalidBonusCampaignTransitionException::class);

        $campaign->activate();
    }

    public function testRejectsDisableFromDisabled(): void
    {
        $campaign = $this->createCampaign(1000, 5);
        $campaign->disable();

        $this->expectException(InvalidBonusCampaignTransitionException::class);

        $campaign->disable();
    }

    public function testRename(): void
    {
        $campaign = $this->createCampaign(1000, 5);
        $campaign->rename('Reload Friday');

        $this->assertSame('Reload Friday', $campaign->name());
    }

    public function testRenameRejectsEmpty(): void
    {
        $campaign = $this->createCampaign(1000, 5);

        $this->expectException(InvalidBonusCampaignException::class);

        $campaign->rename('  ');
    }

    private function createCampaign(
        int $bonusAmountMinor,
        int $wageringMultiplier,
        string $name = 'Welcome bonus',
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): BonusCampaign {
        return BonusCampaign::create(
            BonusCampaignId::fromString(self::CAMPAIGN_ID),
            TenantId::fromString(self::TENANT_ID),
            $name,
            Money::of($bonusAmountMinor, Currency::Usd),
            $wageringMultiplier,
            $createdAt,
        );
    }
}
