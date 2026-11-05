<?php

namespace App\Payment\Application\Handler;

use App\Payment\Application\Command\InitiateWithdrawalCommand;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\PaymentIdempotencyConflictException;
use App\Payment\Domain\Provider\PaymentProviderRequest;
use App\Payment\Domain\Provider\PaymentProviderResolver;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Application\Service\RiskGuard;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class InitiateWithdrawalHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PlayerRepositoryInterface $players,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly PaymentProviderResolver $providers,
        private readonly TransactionManagerInterface $transactions,
        private readonly RiskGuard $risk,
    ) {
    }

    public function __invoke(InitiateWithdrawalCommand $command): void
    {
        $paymentId = PaymentId::fromString($command->paymentId);
        $playerId = PlayerId::fromString($command->playerId);
        $walletId = WalletId::fromString($command->walletId);
        $money = Money::of($command->amountMinor, Currency::from($command->currency));
        $paymentIdempotencyKey = PaymentIdempotencyKey::fromString($command->idempotencyKey);
        $reserveEntryId = LedgerEntryId::fromString($command->reserveEntryId);
        $releaseEntryId = LedgerEntryId::fromString($command->releaseEntryId);
        $player = $this->players->getById($playerId);

        if (!$player->isActive()) {
            throw InvalidPaymentException::playerNotActive($playerId);
        }

        $wallet = $this->wallets->getById($walletId);

        if (!$wallet->playerId()->equals($playerId)) {
            throw InvalidPaymentException::walletPlayerMismatch($paymentId, $walletId);
        }

        if (!$wallet->isActive()) {
            throw InvalidPaymentException::walletNotActive($walletId);
        }

        if ($wallet->currency() !== $money->currency()) {
            throw InvalidPaymentException::walletCurrencyMismatch($paymentId, $walletId);
        }

        $payment = null;
        $existing = $this->payments->findByIdempotencyKey($paymentIdempotencyKey);

        if (null !== $existing) {
            if (!$this->matchesRequest($existing, $paymentId, $playerId, $walletId, $money, $command->provider)) {
                throw PaymentIdempotencyConflictException::forKey($paymentIdempotencyKey);
            }

            if (PaymentStatus::Pending !== $existing->status()) {
                if (PaymentStatus::Failed === $existing->status()) {
                    $this->transactions->transactional(function () use ($walletId, $money, $releaseEntryId): void {
                        $wallet = $this->wallets->getByIdForUpdate($walletId);
                        $this->releaseFunds($wallet, $walletId, $releaseEntryId, $money);
                    });
                }

                return;
            }

            $payment = $existing;
        }

        $this->risk->assertAllowed(
            $player->tenantId(),
            $playerId,
            RiskOperation::Withdrawal,
            $money->amount(),
        );

        if (null === $payment) {
            $payment = Payment::initiate(
                $paymentId,
                $player->tenantId(),
                $playerId,
                $walletId,
                PaymentType::Withdrawal,
                $money,
                $paymentIdempotencyKey,
                $command->provider,
            );

            try {
                $this->payments->save($payment);
            } catch (UniqueConstraintViolationException) {
                $existing = $this->payments->findByIdempotencyKey($paymentIdempotencyKey);
                if (null === $existing || !$this->matchesRequest(
                    $existing,
                    $paymentId,
                    $playerId,
                    $walletId,
                    $money,
                    $command->provider,
                )) {
                    throw PaymentIdempotencyConflictException::forKey($paymentIdempotencyKey);
                }

                if (PaymentStatus::Pending !== $existing->status()) {
                    if (PaymentStatus::Failed === $existing->status()) {
                        $this->transactions->transactional(function () use ($walletId, $money, $releaseEntryId): void {
                            $wallet = $this->wallets->getByIdForUpdate($walletId);
                            $this->releaseFunds($wallet, $walletId, $releaseEntryId, $money);
                        });
                    }

                    return;
                }

                $payment = $existing;
            }
        }

        $this->transactions->transactional(function () use ($walletId, $money, $reserveEntryId): void {
            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $this->reserveFunds($wallet, $walletId, $reserveEntryId, $money);
        });

        $result = $this->providers
            ->resolve($payment->provider())
            ->initiate(new PaymentProviderRequest(
                $paymentId,
                PaymentType::Withdrawal,
                $money,
                $paymentIdempotencyKey,
            ));

        if ($result->isDeclined()) {
            $this->transactions->transactional(function () use (
                $paymentId,
                $walletId,
                $money,
                $releaseEntryId,
                $result,
            ): void {
                $payment = $this->payments->getById($paymentId);

                if (PaymentStatus::Failed === $payment->status()) {
                    $wallet = $this->wallets->getByIdForUpdate($walletId);
                    $this->releaseFunds($wallet, $walletId, $releaseEntryId, $money);

                    return;
                }

                if (PaymentStatus::Pending !== $payment->status()) {
                    return;
                }

                $payment->markProcessing();
                $payment->fail($result->declineReason ?? 'declined');
                $this->payments->save($payment);

                $wallet = $this->wallets->getByIdForUpdate($walletId);
                $this->releaseFunds($wallet, $walletId, $releaseEntryId, $money);
            });

            return;
        }

        if ($result->isSubmitted()) {
            $payment = $this->payments->getById($paymentId);
            if (PaymentStatus::Pending !== $payment->status()) {
                return;
            }

            $payment->markProcessing($result->providerReference);
            $this->payments->save($payment);
        }
    }

    private function reserveFunds(
        Wallet $wallet,
        WalletId $walletId,
        LedgerEntryId $entryId,
        Money $money,
    ): void {
        $existingReserve = $this->ledger->findById($entryId);
        if (null !== $existingReserve) {
            if (
                !$existingReserve->walletId()->equals($walletId)
                || LedgerEntryType::Reserve !== $existingReserve->type()
                || !$existingReserve->amount()->equals($money)
            ) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            return;
        }

        $wallet->reserve($money);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Reserve,
            $money,
            $wallet->availableBalance(),
        ));
    }

    private function releaseFunds(
        Wallet $wallet,
        WalletId $walletId,
        LedgerEntryId $entryId,
        Money $money,
    ): void {
        $existingRelease = $this->ledger->findById($entryId);
        if (null !== $existingRelease) {
            if (
                !$existingRelease->walletId()->equals($walletId)
                || LedgerEntryType::Release !== $existingRelease->type()
                || !$existingRelease->amount()->equals($money)
            ) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            return;
        }

        $wallet->release($money);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Release,
            $money,
            $wallet->availableBalance(),
        ));
    }

    private function matchesRequest(
        Payment $existing,
        PaymentId $paymentId,
        PlayerId $playerId,
        WalletId $walletId,
        Money $amount,
        string $provider,
    ): bool {
        return $existing->id()->equals($paymentId)
            && $existing->playerId()->equals($playerId)
            && $existing->walletId()->equals($walletId)
            && PaymentType::Withdrawal === $existing->type()
            && $existing->amount()->equals($amount)
            && $existing->provider() === trim($provider);
    }
}
