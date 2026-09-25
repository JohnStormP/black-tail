<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Query\ListWalletLedgerQuery;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;

/**
 * @phpstan-type LedgerListResult array{wallet: Wallet, entries: list<LedgerEntry>}
 */
final class ListWalletLedgerHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
    ) {
    }

    /**
     * @return LedgerListResult
     */
    public function __invoke(ListWalletLedgerQuery $query): array
    {
        $walletId = WalletId::fromString($query->walletId);
        $wallet = $this->wallets->getById($walletId);
        $entries = $this->ledger->findByWalletId($walletId);

        return [
            'wallet' => $wallet,
            'entries' => $entries,
        ];
    }
}
