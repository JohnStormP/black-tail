<?php

namespace App\Tests\Payment\Domain\Entity;

use App\Payment\Domain\Entity\Payment;
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
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private const string PAYMENT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string WALLET_ID = '01DXYZ00000000000000000002';
    private const string IDEMPOTENCY_KEY = 'dep-01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testInitiateCreatesPendingDeposit(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');
        $payment = $this->initiate(PaymentType::Deposit, 2500, 'fake', 'prov-ref-1', $createdAt);

        $this->assertTrue($payment->id()->equals(PaymentId::fromString(self::PAYMENT_ID)));
        $this->assertTrue($payment->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertTrue($payment->playerId()->equals(PlayerId::fromString(self::PLAYER_ID)));
        $this->assertTrue($payment->walletId()->equals(WalletId::fromString(self::WALLET_ID)));
        $this->assertSame(PaymentType::Deposit, $payment->type());
        $this->assertSame(2500, $payment->amount()->amount());
        $this->assertSame(Currency::Usd, $payment->amount()->currency());
        $this->assertSame(PaymentStatus::Pending, $payment->status());
        $this->assertSame(self::IDEMPOTENCY_KEY, $payment->idempotencyKey()->toString());
        $this->assertSame('fake', $payment->provider());
        $this->assertSame('prov-ref-1', $payment->providerReference());
        $this->assertNull($payment->failureReason());
        $this->assertFalse($payment->isCompleted());
        $this->assertEquals($createdAt, $payment->createdAt());
    }

    public function testInitiateNormalizesEmptyProviderReferenceToNull(): void
    {
        $payment = $this->initiate(PaymentType::Withdrawal, 1000, 'fake', '   ');

        $this->assertNull($payment->providerReference());
    }

    public function testInitiateRejectsNonPositiveAmount(): void
    {
        $this->expectException(InvalidPaymentException::class);

        $this->initiate(PaymentType::Deposit, 0, 'fake');
    }

    public function testInitiateRejectsEmptyProvider(): void
    {
        $this->expectException(InvalidPaymentException::class);

        $this->initiate(PaymentType::Deposit, 1000, '   ');
    }

    public function testMarkProcessingFromPending(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake');
        $payment->markProcessing();

        $this->assertSame(PaymentStatus::Processing, $payment->status());
    }

    public function testMarkProcessingRejectsFromProcessing(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake');
        $payment->markProcessing();

        $this->expectException(InvalidPaymentTransitionException::class);
        $payment->markProcessing();
    }

    public function testCompleteFromProcessingSetsProviderReference(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake');
        $payment->markProcessing();
        $payment->complete('psp-tx-99');

        $this->assertSame(PaymentStatus::Completed, $payment->status());
        $this->assertTrue($payment->isCompleted());
        $this->assertSame('psp-tx-99', $payment->providerReference());
    }

    public function testCompleteKeepsExistingProviderReferenceWhenNullPassed(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake', 'initial-ref');
        $payment->markProcessing();
        $payment->complete(null);

        $this->assertSame('initial-ref', $payment->providerReference());
    }

    public function testCompleteRejectsFromPending(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake');

        $this->expectException(InvalidPaymentTransitionException::class);
        $payment->complete('psp-tx-99');
    }

    public function testFailFromProcessing(): void
    {
        $payment = $this->initiate(PaymentType::Withdrawal, 1000, 'fake');
        $payment->markProcessing();
        $payment->fail('insufficient_psp_funds');

        $this->assertSame(PaymentStatus::Failed, $payment->status());
        $this->assertSame('insufficient_psp_funds', $payment->failureReason());
        $this->assertFalse($payment->isCompleted());
    }

    public function testFailRejectsEmptyReason(): void
    {
        $payment = $this->initiate(PaymentType::Withdrawal, 1000, 'fake');
        $payment->markProcessing();

        $this->expectException(InvalidPaymentException::class);
        $payment->fail('   ');
    }

    public function testFailRejectsFromPending(): void
    {
        $payment = $this->initiate(PaymentType::Withdrawal, 1000, 'fake');

        $this->expectException(InvalidPaymentTransitionException::class);
        $payment->fail('too_early');
    }

    public function testCancelFromPending(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake');
        $payment->cancel();

        $this->assertSame(PaymentStatus::Cancelled, $payment->status());
    }

    public function testCancelRejectsFromProcessing(): void
    {
        $payment = $this->initiate(PaymentType::Deposit, 1000, 'fake');
        $payment->markProcessing();

        $this->expectException(InvalidPaymentTransitionException::class);
        $payment->cancel();
    }

    private function initiate(
        PaymentType $type,
        int $amountMinor,
        string $provider,
        ?string $providerReference = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00'),
    ): Payment {
        return Payment::initiate(
            PaymentId::fromString(self::PAYMENT_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            $type,
            Money::of($amountMinor, Currency::Usd),
            PaymentIdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            $provider,
            $providerReference,
            $createdAt,
        );
    }
}
