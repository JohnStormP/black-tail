<?php

namespace App\Betting\Domain\Event;

use App\Betting\Domain\ValueObject\BetId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Event\DomainEvent;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class BetRefunded implements DomainEvent
{
    public function __construct(
        private BetId $betId,
        private PlayerId $playerId,
        private WalletId $walletId,
        private Money $amount,
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

    public function walletId(): WalletId
    {
        return $this->walletId;
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
