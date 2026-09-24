<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Command\DebitWalletCommand;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class DebitWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
    ) {
    }

    public function __invoke(DebitWalletCommand $command): void
    {
        $walletId = WalletId::fromString($command->walletId);
        $ledgerEntryId = LedgerEntryId::fromString($command->entryId);
        $wallet = $this->wallets->getById($walletId);
        $money = Money::of($command->amount, $wallet->currency());
        $wallet->debit($money);
        $this->wallets->save($wallet);
        $entry = LedgerEntry::record(
            $ledgerEntryId,
            $wallet->id(),
            LedgerEntryType::Debit,
            $money,
            $wallet->availableBalance(),
        );
        $this->ledger->save($entry);
    }
}
