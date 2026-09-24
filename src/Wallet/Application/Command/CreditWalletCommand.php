<?php

namespace App\Wallet\Application\Command;

final readonly class CreditWalletCommand
{
    public function __construct(
        public string $walletId,
        public int $amount,
    ) {
    }
}
