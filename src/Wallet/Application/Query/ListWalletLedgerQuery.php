<?php

namespace App\Wallet\Application\Query;

final readonly class ListWalletLedgerQuery
{
    public function __construct(
        public string $walletId,
    ) {
    }
}
