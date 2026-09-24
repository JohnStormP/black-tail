<?php

namespace App\Wallet\Domain\Repository;

use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\ValueObject\WalletId;

interface WalletRepositoryInterface
{
    public function save(Wallet $wallet): void;

    public function findById(WalletId $id): ?Wallet;

    /**
     * @throws WalletNotFoundException
     */
    public function getById(WalletId $id): Wallet;
}
