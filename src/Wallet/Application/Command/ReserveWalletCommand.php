<?php

namespace App\Wallet\Application\Command;

final readonly class ReserveWalletCommand
{
    public function __construct(
        public string $walletId,
        public string $entryId,
        public int $amount,
    ) {
    }
}
