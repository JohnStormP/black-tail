<?php

namespace App\Wallet\Application\Command;

final readonly class ReleaseWalletCommand
{
    public function __construct(
        public string $walletId,
        public string $entryId,
        public int $amount,
    ) {
    }
}
