<?php

namespace App\Bonus\Domain\Entity;

use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Exception\InvalidPlayerBonusTransitionException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'player_bonuses')]
#[ORM\UniqueConstraint(name: 'uniq_player_bonuses_player_campaign', columns: ['player_id', 'campaign_id'])]
class PlayerBonus
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'player_id', type: 'string', length: 26)]
    private string $playerId;

    #[ORM\Column(name: 'campaign_id', type: 'string', length: 26)]
    private string $campaignId;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(name: 'granted_amount_minor', type: 'bigint')]
    private int $grantedAmountMinor;

    #[ORM\Column(name: 'currency', type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'wagering_required_minor', type: 'bigint')]
    private int $wageringRequiredMinor;

    #[ORM\Column(name: 'wagering_progress_minor', type: 'bigint')]
    private int $wageringProgressMinor;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: PlayerBonusStatus::class)]
    private PlayerBonusStatus $status;

    #[ORM\Column(name: 'granted_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $grantedAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    private function __construct()
    {
    }

    public static function grant(
        PlayerBonusId $id,
        PlayerId $playerId,
        BonusCampaignId $campaignId,
        TenantId $tenantId,
        Money $grantedAmount,
        Money $wageringRequired,
        \DateTimeImmutable $grantedAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $expiresAt = null,
    ): self {
        if ($grantedAmount->amount() <= 0) {
            throw InvalidPlayerBonusException::nonPositiveGrantedAmount();
        }

        if ($wageringRequired->amount() <= 0) {
            throw InvalidPlayerBonusException::nonPositiveWageringRequired();
        }

        if ($grantedAmount->currency() !== $wageringRequired->currency()) {
            throw InvalidPlayerBonusException::currencyMismatch();
        }

        if (null !== $expiresAt && $expiresAt <= $grantedAt) {
            throw InvalidPlayerBonusException::invalidExpiration();
        }

        $bonus = new self();
        $bonus->id = $id->toString();
        $bonus->playerId = $playerId->toString();
        $bonus->campaignId = $campaignId->toString();
        $bonus->tenantId = $tenantId->toString();
        $bonus->grantedAmountMinor = $grantedAmount->amount();
        $bonus->currency = $grantedAmount->currency()->value;
        $bonus->wageringRequiredMinor = $wageringRequired->amount();
        $bonus->wageringProgressMinor = 0;
        $bonus->status = PlayerBonusStatus::Active;
        $bonus->grantedAt = $grantedAt;
        $bonus->expiresAt = $expiresAt;

        return $bonus;
    }

    public function id(): PlayerBonusId
    {
        return PlayerBonusId::fromString($this->id);
    }

    public function playerId(): PlayerId
    {
        return PlayerId::fromString($this->playerId);
    }

    public function campaignId(): BonusCampaignId
    {
        return BonusCampaignId::fromString($this->campaignId);
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function grantedAmount(): Money
    {
        return Money::of($this->grantedAmountMinor, Currency::from($this->currency));
    }

    public function wageringRequired(): Money
    {
        return Money::of($this->wageringRequiredMinor, Currency::from($this->currency));
    }

    public function wageringProgress(): Money
    {
        return Money::of($this->wageringProgressMinor, Currency::from($this->currency));
    }

    public function remainingWagering(): Money
    {
        $remaining = max(0, $this->wageringRequiredMinor - $this->wageringProgressMinor);

        return Money::of($remaining, Currency::from($this->currency));
    }

    public function status(): PlayerBonusStatus
    {
        return $this->status;
    }

    public function grantedAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return PlayerBonusStatus::Active === $this->status;
    }

    public function expire(\DateTimeImmutable $now = new \DateTimeImmutable()): void
    {
        if (PlayerBonusStatus::Active !== $this->status) {
            throw InvalidPlayerBonusTransitionException::cannotTransition($this->status, PlayerBonusStatus::Expired);
        }

        if (null === $this->expiresAt) {
            throw InvalidPlayerBonusException::missingExpiration($this->id());
        }

        if ($now < $this->expiresAt) {
            throw InvalidPlayerBonusException::notYetExpired($this->id(), $this->expiresAt);
        }

        $this->status = PlayerBonusStatus::Expired;
    }

    public function applyWagering(Money $amount): void
    {
        if (PlayerBonusStatus::Active !== $this->status()) {
            throw InvalidPlayerBonusTransitionException::cannotApplyWagering($this->status());
        }

        if ($amount->amount() <= 0) {
            throw InvalidPlayerBonusException::nonPositiveWageringAmount();
        }
        if ($amount->currency()->value !== $this->currency) {
            throw InvalidPlayerBonusException::currencyMismatch();
        }

        $this->wageringProgressMinor += $amount->amount();

        if ($this->wageringProgressMinor > $this->wageringRequiredMinor) {
            $this->wageringProgressMinor = $this->wageringRequiredMinor;
        }

        if ($this->wageringProgressMinor >= $this->wageringRequiredMinor) {
            $this->status = PlayerBonusStatus::Completed;
        }
    }

    public function cancel(): void
    {
        if (PlayerBonusStatus::Active !== $this->status()) {
            throw InvalidPlayerBonusTransitionException::cannotTransition($this->status(), PlayerBonusStatus::Cancelled);
        }

        $this->status = PlayerBonusStatus::Cancelled;
    }
}
