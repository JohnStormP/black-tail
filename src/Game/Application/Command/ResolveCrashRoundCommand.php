<?php

namespace App\Game\Application\Command;

final readonly class ResolveCrashRoundCommand
{
    /**
     * @param array<string, string> $settleEntryIdsByBetId
     */
    public function __construct(
        public string $roundId,
        public array $settleEntryIdsByBetId,
    ) {
    }
}
