<?php

namespace App\Bonus\Domain\Entity;

use App\Bonus\Domain\Exception\InvalidBonusCampaignException;
use App\Bonus\Domain\Exception\InvalidBonusCampaignTransitionException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\BonusCampaignStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bonus_campaigns')]
#[ORM\Index(name: 'idx_bonus_campaigns_tenant_id', columns: ['tenant_id'])]
class BonusCampaign
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'bonus_amount_minor', type: 'bigint')]
    private int $bonusAmountMinor;

    #[ORM\Column(name: 'currency', type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'wagering_multiplier', type: 'integer')]
    private int $wageringMultiplier;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: BonusCampaignStatus::class)]
    private BonusCampaignStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        BonusCampaignId $id,
        TenantId $tenantId,
        string $name,
        Money $bonusAmount,
        int $wageringMultiplier,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $name = trim($name);
        if ('' === $name) {
            throw InvalidBonusCampaignException::emptyName();
        }

        if ($bonusAmount->amount() <= 0) {
            throw InvalidBonusCampaignException::nonPositiveBonusAmount();
        }

        if ($wageringMultiplier <= 0) {
            throw InvalidBonusCampaignException::invalidWageringMultiplier($wageringMultiplier);
        }
        $campaign = new self();
        $campaign->id = $id->toString();
        $campaign->tenantId = $tenantId->toString();
        $campaign->name = $name;
        $campaign->bonusAmountMinor = $bonusAmount->amount();
        $campaign->currency = $bonusAmount->currency()->value;
        $campaign->wageringMultiplier = $wageringMultiplier;
        $campaign->status = BonusCampaignStatus::Draft;
        $campaign->createdAt = $createdAt;

        return $campaign;
    }

    public function id(): BonusCampaignId
    {
        return BonusCampaignId::fromString($this->id);
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function bonusAmount(): Money
    {
        return Money::of($this->bonusAmountMinor, Currency::from($this->currency));
    }

    public function wageringMultiplier(): int
    {
        return $this->wageringMultiplier;
    }

    public function requiredWager(): Money
    {
        return Money::of($this->bonusAmountMinor * $this->wageringMultiplier, Currency::from($this->currency));
    }

    public function status(): BonusCampaignStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return BonusCampaignStatus::Active === $this->status;
    }

    public function activate(): void
    {
        if (BonusCampaignStatus::Draft !== $this->status) {
            throw InvalidBonusCampaignTransitionException::cannotTransition($this->status, BonusCampaignStatus::Active);
        }

        $this->status = BonusCampaignStatus::Active;
    }

    public function disable(): void
    {
        if (BonusCampaignStatus::Disabled === $this->status) {
            throw InvalidBonusCampaignTransitionException::cannotTransition($this->status, BonusCampaignStatus::Disabled);
        }

        $this->status = BonusCampaignStatus::Disabled;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ('' === $name) {
            throw InvalidBonusCampaignException::emptyName();
        }

        $this->name = $name;
    }
}
