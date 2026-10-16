<?php

namespace App\Bonus\Application\Command;

final readonly class GrantPlayerBonusCommand
{
    public function __construct(
        public string $playerBonusId,
        public string $playerId,
        public string $campaignId,
        public ?string $expiresAt = null,
    ) {
    }
}
