<?php

namespace App\Wallet\Application\Handler;

use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Application\Command\CreditWalletCommand;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class CreditWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function __invoke(CreditWalletCommand $command): void
    {
        $this->transactions->transactional(function () use ($command): void {
            $walletId = WalletId::fromString($command->walletId);
            $ledgerEntryId = LedgerEntryId::fromString($command->entryId);
            $wallet = $this->wallets->getByIdForUpdate($walletId);
            $money = Money::of($command->amount, $wallet->currency());
            $wallet->credit($money);
            $this->wallets->save($wallet);
            $entry = LedgerEntry::record(
                $ledgerEntryId,
                $wallet->id(),
                LedgerEntryType::Credit,
                $money,
                $wallet->availableBalance(),
            );
            $this->ledger->save($entry);
        });
    }
}
