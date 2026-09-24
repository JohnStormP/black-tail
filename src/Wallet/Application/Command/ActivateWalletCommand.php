<?php

namespace App\Wallet\Application\Command;

final readonly class ActivateWalletCommand
{
    public function __construct(
        public string $walletId,
    ) {
    }
}
