<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Command\FreezeWalletCommand;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;

final class FreezeWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function __invoke(FreezeWalletCommand $command): void
    {
        $id = WalletId::fromString($command->walletId);
        $wallet = $this->wallets->getById($id);
        $wallet->freeze();
        $this->wallets->save($wallet);
    }
}
