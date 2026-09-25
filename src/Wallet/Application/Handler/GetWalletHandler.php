<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Query\GetWalletQuery;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;

final class GetWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function __invoke(GetWalletQuery $query): Wallet
    {
        return $this->wallets->getById(WalletId::fromString($query->walletId));
    }
}
