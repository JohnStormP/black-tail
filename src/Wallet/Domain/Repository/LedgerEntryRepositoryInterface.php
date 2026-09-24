<?php

namespace App\Wallet\Domain\Repository;

use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerEntryNotFoundException;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\WalletId;

interface LedgerEntryRepositoryInterface
{
    public function save(LedgerEntry $entry): void;

    public function findById(LedgerEntryId $id): ?LedgerEntry;

    /**
     * @throws LedgerEntryNotFoundException
     */
    public function getById(LedgerEntryId $id): LedgerEntry;

    /**
     * @return list<LedgerEntry>
     */
    public function findByWalletId(WalletId $walletId): array;
}
