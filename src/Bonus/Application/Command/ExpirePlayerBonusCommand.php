<?php

namespace App\Bonus\Application\Command;

final readonly class ExpirePlayerBonusCommand
{
    public function __construct(
        public string $playerBonusId,
        public ?string $now = null,
        public ?string $forfeitEntryId = null,
    ) {
    }
}
