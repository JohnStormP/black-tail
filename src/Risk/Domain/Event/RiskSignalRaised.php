<?php

namespace App\Risk\Domain\Event;

use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Shared\Domain\Event\DomainEvent;
use App\Tenant\Domain\ValueObject\TenantId;

final readonly class RiskSignalRaised implements DomainEvent
{
    /**
     * @param list<RiskRuleId> $matchedRuleIds
     */
    public function __construct(
        private TenantId $tenantId,
        private PlayerId $playerId,
        private RiskOperation $operation,
        private RiskRuleAction $action,
        private array $matchedRuleIds,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function playerId(): PlayerId
    {
        return $this->playerId;
    }

    public function operation(): RiskOperation
    {
        return $this->operation;
    }

    public function action(): RiskRuleAction
    {
        return $this->action;
    }

    /**
     * @return list<RiskRuleId>
     */
    public function matchedRuleIds(): array
    {
        return $this->matchedRuleIds;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
