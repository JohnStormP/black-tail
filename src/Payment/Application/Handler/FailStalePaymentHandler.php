<?php

namespace App\Payment\Application\Handler;

use App\Payment\Application\Command\FailStalePaymentCommand;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;

final class FailStalePaymentHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(FailStalePaymentCommand $command): void
    {
        $paymentId = PaymentId::fromString($command->paymentId);
        $payment = $this->payments->getById($paymentId);
        $staleBefore = new \DateTimeImmutable($command->staleBefore);
        $releaseEntryId = $command->releaseEntryId;

        if ($payment->createdAt() >= $staleBefore) {
            throw InvalidPaymentException::notYetStale($paymentId, $payment->createdAt(), $staleBefore);
        }

        if (PaymentStatus::Cancelled === $payment->status() || PaymentStatus::Failed === $payment->status()) {
            return;
        }

        if (PaymentStatus::Completed === $payment->status()) {
            throw InvalidPaymentException::cannotFailStaleCompleted($paymentId);
        }

        if (PaymentStatus::Pending === $payment->status()) {
            $payment->cancel();
            $this->payments->save($payment);

            return;
        }

        if (
            PaymentType::Withdrawal === $payment->type()
            && (null === $releaseEntryId || '' === trim($releaseEntryId))
        ) {
            throw InvalidPaymentException::releaseEntryRequiredForFailedWithdrawal();
        }

        $this->transactions->transactional(function () use ($command, $payment): void {
            $wallet = $this->wallets->getByIdForUpdate($payment->walletId());
            $payment = $this->payments->getByIdForUpdate($payment->id());

            if (PaymentStatus::Failed === $payment->status()) {
                return;
            }

            $payment->fail($command->reason);
            $this->payments->save($payment);

            if (PaymentType::Withdrawal !== $payment->type()) {
                return;
            }

            $releaseEntryId = LedgerEntryId::fromString((string) $command->releaseEntryId);
            $money = $payment->amount();
            $existingRelease = $this->ledger->findById($releaseEntryId);

            if (null !== $existingRelease) {
                if (
                    !$existingRelease->walletId()->equals($wallet->id())
                    || LedgerEntryType::Release !== $existingRelease->type()
                    || !$existingRelease->amount()->equals($money)
                ) {
                    throw LedgerIdempotencyConflictException::forEntry($releaseEntryId);
                }

                return;
            }

            $wallet->release($money);
            $this->wallets->save($wallet);
            $this->ledger->save(LedgerEntry::record(
                $releaseEntryId,
                $wallet->id(),
                LedgerEntryType::Release,
                $money,
                $wallet->availableBalance(),
            ));
        });
    }
}
