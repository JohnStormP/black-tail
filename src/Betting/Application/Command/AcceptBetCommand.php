<?php

namespace App\Betting\Application\Command;

final readonly class AcceptBetCommand
{
    public function __construct(
        public string $betId,
        public string $walletId,
        public string $reserveEntryId,
    ) {
    }
}
