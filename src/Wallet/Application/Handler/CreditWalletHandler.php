<?php

namespace App\Wallet\Application\Handler;

use App\Wallet\Application\Command\CreditWalletCommand;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class CreditWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function __invoke(CreditWalletCommand $command): void
    {
        $id = WalletId::fromString($command->walletId);
        $wallet = $this->wallets->getById($id);
        $money = Money::of($command->amount, $wallet->currency());
        $wallet->credit($money);
        $this->wallets->save($wallet);
    }
}
