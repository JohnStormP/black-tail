<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Command\ActivateWalletCommand;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;

final class ActivateWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function __invoke(ActivateWalletCommand $command): void
    {
        $id = WalletId::fromString($command->walletId);
        $wallet = $this->wallets->getById($id);
        $wallet->activate();
        $this->wallets->save($wallet);
    }
}
