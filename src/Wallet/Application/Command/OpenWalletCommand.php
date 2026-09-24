<?php

namespace App\Wallet\Application\Command;

final readonly class OpenWalletCommand
{
    public function __construct(
        public string $walletId,
        public string $playerId,
        public string $currency,
    ) {
    }
}
