<?php

namespace App\Bonus\Application\Command;

final readonly class CancelPlayerBonusCommand
{
    public function __construct(
        public string $playerBonusId,
        public ?string $forfeitEntryId = null,
    ) {
    }
}
