<?php

namespace App\Payment\Application\Handler;

use App\Payment\Application\Command\HandlePaymentWebhookCommand;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Payment\Domain\ValueObject\PaymentWebhookOutcome;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;

final class HandlePaymentWebhookHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(HandlePaymentWebhookCommand $command): void
    {
        $providerReference = trim($command->providerReference);

        if ('' === $providerReference) {
            throw InvalidPaymentException::emptyProviderReference();
        }

        $outcome = PaymentWebhookOutcome::from($command->outcome);
        $ledgerEntryId = LedgerEntryId::fromString($command->ledgerEntryId);
        $payment = $this->payments->findByProviderReference($command->provider, $providerReference);

        if (null === $payment) {
            throw PaymentNotFoundException::withProviderReference($command->provider, $providerReference);
        }

        if ($this->isDuplicateWebhook($payment, $outcome)) {
            return;
        }

        if (
            PaymentWebhookOutcome::Failed === $outcome
            && PaymentType::Withdrawal === $payment->type()
            && (null === $command->releaseEntryId || '' === trim($command->releaseEntryId))
        ) {
            throw InvalidPaymentException::releaseEntryRequiredForFailedWithdrawal();
        }

        $this->transactions->transactional(function () use (
            $payment,
            $outcome,
            $command,
            $ledgerEntryId,
        ): void {
            $wallet = $this->wallets->getByIdForUpdate($payment->walletId());
            $payment = $this->payments->getByIdForUpdate($payment->id());

            if ($this->isDuplicateWebhook($payment, $outcome)) {
                return;
            }

            if (PaymentStatus::Processing !== $payment->status()) {
                throw InvalidPaymentException::webhookCannotApply($payment->id(), $payment->status()->value);
            }

            $money = $payment->amount();

            if (PaymentWebhookOutcome::Completed === $outcome) {
                if (PaymentType::Deposit === $payment->type()) {
                    $this->applyWalletEffect(
                        $wallet,
                        $ledgerEntryId,
                        LedgerEntryType::Deposit,
                        $money,
                        static function (Wallet $wallet, Money $money): void {
                            $wallet->credit($money);
                        },
                    );
                } else {
                    $this->applyWalletEffect(
                        $wallet,
                        $ledgerEntryId,
                        LedgerEntryType::Settle,
                        $money,
                        static function (Wallet $wallet, Money $money): void {
                            $wallet->settle($money);
                        },
                    );
                }

                $payment->complete();
                $this->payments->save($payment);

                return;
            }

            $payment->fail($command->failureReason ?? 'webhook_failed');
            $this->payments->save($payment);

            if (PaymentType::Withdrawal === $payment->type()) {
                $releaseEntryId = LedgerEntryId::fromString((string) $command->releaseEntryId);
                $this->applyWalletEffect(
                    $wallet,
                    $releaseEntryId,
                    LedgerEntryType::Release,
                    $money,
                    static function (Wallet $wallet, Money $money): void {
                        $wallet->release($money);
                    },
                );
            }
        });
    }

    private function isDuplicateWebhook(Payment $payment, PaymentWebhookOutcome $outcome): bool
    {
        return (PaymentStatus::Completed === $payment->status() && PaymentWebhookOutcome::Completed === $outcome)
            || (PaymentStatus::Failed === $payment->status() && PaymentWebhookOutcome::Failed === $outcome);
    }

    private function applyWalletEffect(
        Wallet $wallet,
        LedgerEntryId $entryId,
        LedgerEntryType $type,
        Money $money,
        callable $mutate,
    ): void {
        $existing = $this->ledger->findById($entryId);
        if (null !== $existing) {
            if (
                !$existing->walletId()->equals($wallet->id())
                || $type !== $existing->type()
                || !$existing->amount()->equals($money)
            ) {
                throw LedgerIdempotencyConflictException::forEntry($entryId);
            }

            return;
        }

        $mutate($wallet, $money);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            $type,
            $money,
            $wallet->availableBalance(),
        ));
    }
}
