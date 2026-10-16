<?php

namespace App\Tests\Bonus\Domain\Entity;

use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Exception\InvalidPlayerBonusTransitionException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PlayerBonusTest extends TestCase
{
    private const string BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string CAMPAIGN_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';

    public function testGrantCreatesActiveBonus(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $bonus = $this->grant(1000, 5000, $grantedAt);

        $this->assertTrue($bonus->id()->equals(PlayerBonusId::fromString(self::BONUS_ID)));
        $this->assertTrue($bonus->playerId()->equals(PlayerId::fromString(self::PLAYER_ID)));
        $this->assertTrue($bonus->campaignId()->equals(BonusCampaignId::fromString(self::CAMPAIGN_ID)));
        $this->assertTrue($bonus->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertSame(1000, $bonus->grantedAmount()->amount());
        $this->assertSame(Currency::Usd, $bonus->grantedAmount()->currency());
        $this->assertSame(5000, $bonus->wageringRequired()->amount());
        $this->assertSame(0, $bonus->wageringProgress()->amount());
        $this->assertSame(5000, $bonus->remainingWagering()->amount());
        $this->assertSame(PlayerBonusStatus::Active, $bonus->status());
        $this->assertTrue($bonus->isActive());
        $this->assertEquals($grantedAt, $bonus->grantedAt());
    }

    public function testGrantRejectsZeroGrantedAmount(): void
    {
        $this->expectException(InvalidPlayerBonusException::class);

        $this->grant(0, 5000);
    }

    public function testGrantRejectsZeroWageringRequired(): void
    {
        $this->expectException(InvalidPlayerBonusException::class);

        $this->grant(1000, 0);
    }

    public function testGrantRejectsCurrencyMismatch(): void
    {
        $this->expectException(InvalidPlayerBonusException::class);

        PlayerBonus::grant(
            PlayerBonusId::fromString(self::BONUS_ID),
            PlayerId::fromString(self::PLAYER_ID),
            BonusCampaignId::fromString(self::CAMPAIGN_ID),
            TenantId::fromString(self::TENANT_ID),
            Money::of(1000, Currency::Usd),
            Money::of(5000, Currency::Eur),
        );
    }

    public function testApplyWageringIncreasesProgress(): void
    {
        $bonus = $this->grant(1000, 5000);
        $bonus->applyWagering(Money::of(2000, Currency::Usd));

        $this->assertSame(2000, $bonus->wageringProgress()->amount());
        $this->assertSame(3000, $bonus->remainingWagering()->amount());
        $this->assertSame(PlayerBonusStatus::Active, $bonus->status());
    }

    public function testApplyWageringCompletesWhenRequirementMet(): void
    {
        $bonus = $this->grant(1000, 5000);
        $bonus->applyWagering(Money::of(5000, Currency::Usd));

        $this->assertSame(5000, $bonus->wageringProgress()->amount());
        $this->assertSame(0, $bonus->remainingWagering()->amount());
        $this->assertSame(PlayerBonusStatus::Completed, $bonus->status());
        $this->assertFalse($bonus->isActive());
    }

    public function testApplyWageringCapsProgressAtRequired(): void
    {
        $bonus = $this->grant(1000, 5000);
        $bonus->applyWagering(Money::of(7000, Currency::Usd));

        $this->assertSame(5000, $bonus->wageringProgress()->amount());
        $this->assertSame(PlayerBonusStatus::Completed, $bonus->status());
    }

    public function testRejectsApplyWageringWhenNotActive(): void
    {
        $bonus = $this->grant(1000, 5000);
        $bonus->cancel();

        $this->expectException(InvalidPlayerBonusTransitionException::class);

        $bonus->applyWagering(Money::of(100, Currency::Usd));
    }

    public function testRejectsNonPositiveWageringAmount(): void
    {
        $bonus = $this->grant(1000, 5000);

        $this->expectException(InvalidPlayerBonusException::class);

        $bonus->applyWagering(Money::of(0, Currency::Usd));
    }

    public function testCancelFromActive(): void
    {
        $bonus = $this->grant(1000, 5000);
        $bonus->cancel();

        $this->assertSame(PlayerBonusStatus::Cancelled, $bonus->status());
        $this->assertFalse($bonus->isActive());
    }

    public function testRejectsCancelFromCompleted(): void
    {
        $bonus = $this->grant(1000, 5000);
        $bonus->applyWagering(Money::of(5000, Currency::Usd));

        $this->expectException(InvalidPlayerBonusTransitionException::class);

        $bonus->cancel();
    }

    public function testGrantStoresExpiresAt(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $expiresAt = new \DateTimeImmutable('2026-01-22 12:00:00');
        $bonus = $this->grant(1000, 5000, $grantedAt, $expiresAt);

        $this->assertEquals($expiresAt, $bonus->expiresAt());
    }

    public function testGrantRejectsExpiresAtNotAfterGrantedAt(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $this->expectException(InvalidPlayerBonusException::class);

        $this->grant(1000, 5000, $grantedAt, $grantedAt);
    }

    public function testExpireFromActiveWhenDue(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $expiresAt = new \DateTimeImmutable('2026-01-22 12:00:00');
        $bonus = $this->grant(1000, 5000, $grantedAt, $expiresAt);

        $bonus->expire(new \DateTimeImmutable('2026-01-22 12:00:00'));

        $this->assertSame(PlayerBonusStatus::Expired, $bonus->status());
        $this->assertFalse($bonus->isActive());
    }

    public function testRejectsExpireBeforeDue(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $expiresAt = new \DateTimeImmutable('2026-01-22 12:00:00');
        $bonus = $this->grant(1000, 5000, $grantedAt, $expiresAt);

        $this->expectException(InvalidPlayerBonusException::class);

        $bonus->expire(new \DateTimeImmutable('2026-01-20 12:00:00'));
    }

    public function testRejectsExpireWithoutExpiresAt(): void
    {
        $bonus = $this->grant(1000, 5000);

        $this->expectException(InvalidPlayerBonusException::class);

        $bonus->expire();
    }

    public function testRejectsExpireWhenNotActive(): void
    {
        $grantedAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $expiresAt = new \DateTimeImmutable('2026-01-22 12:00:00');
        $bonus = $this->grant(1000, 5000, $grantedAt, $expiresAt);
        $bonus->cancel();

        $this->expectException(InvalidPlayerBonusTransitionException::class);

        $bonus->expire(new \DateTimeImmutable('2026-01-23 12:00:00'));
    }

    private function grant(
        int $grantedMinor,
        int $wageringRequiredMinor,
        \DateTimeImmutable $grantedAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $expiresAt = null,
    ): PlayerBonus {
        return PlayerBonus::grant(
            PlayerBonusId::fromString(self::BONUS_ID),
            PlayerId::fromString(self::PLAYER_ID),
            BonusCampaignId::fromString(self::CAMPAIGN_ID),
            TenantId::fromString(self::TENANT_ID),
            Money::of($grantedMinor, Currency::Usd),
            Money::of($wageringRequiredMinor, Currency::Usd),
            $grantedAt,
            $expiresAt,
        );
    }
}
