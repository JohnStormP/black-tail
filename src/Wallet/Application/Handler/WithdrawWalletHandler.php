<?php

namespace App\Wallet\Application\Handler;

use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Application\Command\WithdrawWalletCommand;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class WithdrawWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(WithdrawWalletCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $walletId = WalletId::fromString($command->walletId);
            $ledgerEntryId = LedgerEntryId::fromString($command->entryId);
            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $money = Money::of($command->amount, $wallet->currency());
            $existing = $this->ledger->findById($ledgerEntryId);
            if (null !== $existing) {
                if (
                    $existing->walletId()->equals($walletId)
                    && LedgerEntryType::Withdrawal === $existing->type()
                    && $existing->amount()->equals($money)
                ) {
                    return;
                }
                throw LedgerIdempotencyConflictException::forEntry($ledgerEntryId);
            }
            $wallet->debit($money);
            $this->wallets->save($wallet);
            $entry = LedgerEntry::record(
                $ledgerEntryId,
                $wallet->id(),
                LedgerEntryType::Withdrawal,
                $money,
                $wallet->availableBalance(),
            );
            $this->ledger->save($entry);
        });
    }
}
