<?php

namespace App\Wallet\Application\Handler;

use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\WalletId;

final class OpenWalletHandler
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
    ) {
    }

    public function __invoke(OpenWalletCommand $command): void
    {
        $walletId = WalletId::fromString($command->walletId);
        $playerId = PlayerId::fromString($command->playerId);
        $currency = Currency::from($command->currency);
        $wallet = Wallet::open($walletId, $playerId, $currency);
        $this->wallets->save($wallet);
    }
}
