<?php

namespace App\Wallet\Application\Command;

final readonly class FreezeWalletCommand
{
    public function __construct(
        public string $walletId,
    ) {
    }
}
