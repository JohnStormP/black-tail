<?php

namespace App\Bonus\Domain\Event;

use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Event\DomainEvent;
use App\Wallet\Domain\ValueObject\Money;

final readonly class PlayerBonusCompleted implements DomainEvent
{
    public function __construct(
        private PlayerBonusId $playerBonusId,
        private PlayerId $playerId,
        private BonusCampaignId $campaignId,
        private Money $grantedAmount,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function playerBonusId(): PlayerBonusId
    {
        return $this->playerBonusId;
    }

    public function playerId(): PlayerId
    {
        return $this->playerId;
    }

    public function campaignId(): BonusCampaignId
    {
        return $this->campaignId;
    }

    public function grantedAmount(): Money
    {
        return $this->grantedAmount;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
