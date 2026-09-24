<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Command\DebitWalletCommand;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class DebitWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function __invoke(DebitWalletCommand $command): void
    {
        $id = WalletId::fromString($command->walletId);
        $wallet = $this->wallets->getById($id);
        $money = Money::of($command->amount, $wallet->currency());
        $wallet->debit($money);
        $this->wallets->save($wallet);
    }
}
