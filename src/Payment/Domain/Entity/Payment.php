<?php

namespace App\Payment\Domain\Entity;

use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\InvalidPaymentTransitionException;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
#[ORM\UniqueConstraint(name: 'uniq_payments_idempotency_key', columns: ['idempotency_key'])]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(name: 'player_id', type: 'string', length: 26)]
    private string $playerId;

    #[ORM\Column(name: 'wallet_id', type: 'string', length: 26)]
    private string $walletId;

    #[ORM\Column(name: 'type', type: 'string', length: 32, enumType: PaymentType::class)]
    private PaymentType $type;

    #[ORM\Column(name: 'amount_minor', type: 'bigint')]
    private int $amountMinor;

    #[ORM\Column(name: 'currency', type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: PaymentStatus::class)]
    private PaymentStatus $status;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 64)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'provider', type: 'string', length: 64)]
    private string $provider;

    #[ORM\Column(name: 'provider_reference', type: 'string', length: 128, nullable: true)]
    private ?string $providerReference;

    #[ORM\Column(name: 'failure_reason', type: 'string', length: 255, nullable: true)]
    private ?string $failureReason;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function initiate(
        PaymentId $id,
        TenantId $tenantId,
        PlayerId $playerId,
        WalletId $walletId,
        PaymentType $type,
        Money $amount,
        PaymentIdempotencyKey $idempotencyKey,
        string $provider,
        ?string $providerReference = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        if ($amount->amount() <= 0) {
            throw InvalidPaymentException::nonPositiveAmount();
        }

        $provider = trim($provider);
        if ('' === $provider) {
            throw InvalidPaymentException::emptyProvider();
        }

        $providerReference = trim((string) $providerReference) ?: null;

        $payment = new self();
        $payment->id = $id->toString();
        $payment->tenantId = $tenantId->toString();
        $payment->playerId = $playerId->toString();
        $payment->walletId = $walletId->toString();
        $payment->type = $type;
        $payment->amountMinor = $amount->amount();
        $payment->currency = $amount->currency()->value;
        $payment->idempotencyKey = $idempotencyKey->toString();
        $payment->provider = $provider;
        $payment->providerReference = $providerReference;
        $payment->status = PaymentStatus::Pending;
        $payment->failureReason = null;
        $payment->createdAt = $createdAt;

        return $payment;
    }

    public function id(): PaymentId
    {
        return PaymentId::fromString($this->id);
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function playerId(): PlayerId
    {
        return PlayerId::fromString($this->playerId);
    }

    public function walletId(): WalletId
    {
        return WalletId::fromString($this->walletId);
    }

    public function type(): PaymentType
    {
        return $this->type;
    }

    public function amount(): Money
    {
        return Money::of($this->amountMinor, Currency::from($this->currency));
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function idempotencyKey(): PaymentIdempotencyKey
    {
        return PaymentIdempotencyKey::fromString($this->idempotencyKey);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCompleted(): bool
    {
        return PaymentStatus::Completed === $this->status;
    }

    public function markProcessing(?string $providerReference = null): void
    {
        if (PaymentStatus::Pending !== $this->status) {
            throw InvalidPaymentTransitionException::cannotTransition($this->status, PaymentStatus::Processing);
        }

        if (null !== $providerReference) {
            $this->providerReference = trim($providerReference) ?: null;
        }

        $this->status = PaymentStatus::Processing;
    }

    public function complete(?string $providerReference = null): void
    {
        if (PaymentStatus::Processing !== $this->status) {
            throw InvalidPaymentTransitionException::cannotTransition($this->status, PaymentStatus::Completed);
        }

        if (null !== $providerReference) {
            $this->providerReference = trim($providerReference) ?: null;
        }

        $this->status = PaymentStatus::Completed;
    }

    public function fail(string $reason): void
    {
        if (PaymentStatus::Processing !== $this->status) {
            throw InvalidPaymentTransitionException::cannotTransition($this->status, PaymentStatus::Failed);
        }

        $reason = trim($reason);
        if ('' === $reason) {
            throw InvalidPaymentException::emptyFailureReason();
        }

        $this->failureReason = $reason;
        $this->status = PaymentStatus::Failed;
    }

    public function cancel(): void
    {
        if (PaymentStatus::Pending !== $this->status) {
            throw InvalidPaymentTransitionException::cannotTransition($this->status, PaymentStatus::Cancelled);
        }

        $this->status = PaymentStatus::Cancelled;
    }
}
