<?php

namespace App\Wallet\Application\Query;

final readonly class GetWalletQuery
{
    public function __construct(
        public string $walletId,
    ) {
    }
}
