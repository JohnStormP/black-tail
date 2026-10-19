<?php

namespace App\Risk\Domain\Entity;

use App\Risk\Domain\Exception\InvalidRiskRuleException;
use App\Risk\Domain\Exception\InvalidRiskRuleTransitionException;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'risk_rules')]
class RiskRule
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'type', type: 'string', length: 64, enumType: RiskRuleType::class)]
    private RiskRuleType $type;

    #[ORM\Column(name: 'threshold', type: 'bigint')]
    private int $threshold;

    #[ORM\Column(name: 'window_seconds', type: 'integer', nullable: true)]
    private ?int $windowSeconds;

    #[ORM\Column(name: 'action', type: 'string', length: 32, enumType: RiskRuleAction::class)]
    private RiskRuleAction $action;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: RiskRuleStatus::class)]
    private RiskRuleStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        RiskRuleId $id,
        TenantId $tenantId,
        string $name,
        RiskRuleType $type,
        int $threshold,
        RiskRuleAction $action,
        ?int $windowSeconds = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $name = trim($name);

        if ('' === $name) {
            throw InvalidRiskRuleException::emptyName();
        }

        if ($threshold <= 0) {
            throw InvalidRiskRuleException::nonPositiveThreshold();
        }

        if (RiskRuleType::BetVelocity === $type && null === $windowSeconds) {
            throw InvalidRiskRuleException::windowRequiredForVelocity();
        }

        if (null !== $windowSeconds && $windowSeconds <= 0) {
            throw InvalidRiskRuleException::nonPositiveWindowSeconds();
        }

        $rule = new self();
        $rule->id = $id->toString();
        $rule->tenantId = $tenantId->toString();
        $rule->name = $name;
        $rule->type = $type;
        $rule->threshold = $threshold;
        $rule->action = $action;
        $rule->status = RiskRuleStatus::Draft;
        $rule->windowSeconds = $windowSeconds;
        $rule->createdAt = $createdAt;

        return $rule;
    }

    public function id(): RiskRuleId
    {
        return RiskRuleId::fromString($this->id);
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): RiskRuleType
    {
        return $this->type;
    }

    public function threshold(): int
    {
        return $this->threshold;
    }

    public function windowSeconds(): ?int
    {
        return $this->windowSeconds;
    }

    public function action(): RiskRuleAction
    {
        return $this->action;
    }

    public function status(): RiskRuleStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return RiskRuleStatus::Active === $this->status;
    }

    public function activate(): void
    {
        if (RiskRuleStatus::Draft !== $this->status) {
            throw InvalidRiskRuleTransitionException::cannotTransition($this->status, RiskRuleStatus::Active);
        }

        $this->status = RiskRuleStatus::Active;
    }

    public function disable(): void
    {
        if (RiskRuleStatus::Disabled === $this->status) {
            throw InvalidRiskRuleTransitionException::cannotTransition($this->status, RiskRuleStatus::Disabled);
        }

        $this->status = RiskRuleStatus::Disabled;
    }
}
