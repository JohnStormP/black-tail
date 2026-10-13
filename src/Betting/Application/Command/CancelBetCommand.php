<?php

namespace App\Betting\Application\Command;

final readonly class CancelBetCommand
{
    public function __construct(
        public string $betId,
    ) {
    }
}
