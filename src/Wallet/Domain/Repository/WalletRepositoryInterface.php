<?php

namespace App\Wallet\Domain\Repository;

use App\Player\Domain\ValueObject\PlayerId;
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

    /**
     * @throws WalletNotFoundException
     */
    public function getByIdForUpdate(WalletId $id): Wallet;

    /**
     * @return list<Wallet>
     */
    public function findByPlayerId(PlayerId $playerId): array;
}
