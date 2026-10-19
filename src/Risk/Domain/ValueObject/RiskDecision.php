<?php

namespace App\Risk\Domain\ValueObject;

final readonly class RiskDecision
{
    /**
     * @param list<RiskRuleId> $matchedRuleIds
     */
    private function __construct(
        private ?RiskRuleAction $action,
        private array $matchedRuleIds,
    ) {
    }

    public static function allow(): self
    {
        return new self(null, []);
    }

    /**
     * @param list<RiskRuleId> $matchedRuleIds
     */
    public static function of(RiskRuleAction $action, array $matchedRuleIds): self
    {
        return new self($action, $matchedRuleIds);
    }

    public function isAllowed(): bool
    {
        return RiskRuleAction::Block !== $this->action;
    }

    public function isBlocked(): bool
    {
        return RiskRuleAction::Block === $this->action;
    }

    public function isFlagged(): bool
    {
        return RiskRuleAction::Flag === $this->action;
    }

    public function requiresManualReview(): bool
    {
        return RiskRuleAction::ManualReview === $this->action;
    }

    public function action(): ?RiskRuleAction
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
}
