<?php

namespace App\Betting\Domain\Event;

use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetSettlementOutcome;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Event\DomainEvent;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class BetSettled implements DomainEvent
{
    public function __construct(
        private BetId $betId,
        private PlayerId $playerId,
        private WalletId $walletId,
        private BetSettlementOutcome $outcome,
        private Money $stake,
        private int $payoutMinor,
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

    public function walletId(): WalletId
    {
        return $this->walletId;
    }

    public function outcome(): BetSettlementOutcome
    {
        return $this->outcome;
    }

    public function stake(): Money
    {
        return $this->stake;
    }

    public function payoutMinor(): int
    {
        return $this->payoutMinor;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
