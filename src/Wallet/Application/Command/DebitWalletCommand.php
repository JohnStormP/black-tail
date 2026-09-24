<?php

namespace App\Wallet\Application\Command;

final readonly class DebitWalletCommand
{
    public function __construct(
        public string $walletId,
        public int $amount,
    ) {
    }
}
