<?php

namespace App\Betting\Domain\Event;

use App\Betting\Domain\ValueObject\BetId;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Event\DomainEvent;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Money;

final readonly class BetPlaced implements DomainEvent
{
    public function __construct(
        private BetId $betId,
        private PlayerId $playerId,
        private GameId $gameId,
        private GameRoundId $roundId,
        private Money $amount,
        private TenantId $tenantId,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function betId(): BetId
    {
        return $this->betId;
    }

    public function playerId(): PlayerId
    {
        return $this->playerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function gameId(): GameId
    {
        return $this->gameId;
    }

    public function roundId(): GameRoundId
    {
        return $this->roundId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
