<?php

namespace App\Game\Domain\Event;

use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Event\DomainEvent;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Money;

final readonly class LuckyReelsSpun implements DomainEvent
{
    public function __construct(
        private GameRoundId $roundId,
        private GameId $gameId,
        private PlayerId $playerId,
        private TenantId $tenantId,
        private Money $stake,
        private int $payoutMinor,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function roundId(): GameRoundId
    {
        return $this->roundId;
    }

    public function gameId(): GameId
    {
        return $this->gameId;
    }

    public function playerId(): PlayerId
    {
        return $this->playerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function stake(): Money
    {
        return $this->stake;
    }

    public function payoutMinor(): int
    {
        return $this->payoutMinor;
    }

    public function outcome(): string
    {
        return $this->payoutMinor > 0 ? 'won' : 'lost';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
