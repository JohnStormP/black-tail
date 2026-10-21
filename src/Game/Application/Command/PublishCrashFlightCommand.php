<?php

namespace App\Game\Application\Command;

final readonly class PublishCrashFlightCommand
{
    /**
     * @param positive-int $stepHundredths
     */
    public function __construct(
        public string $roundId,
        public int $stepHundredths = 5,
    ) {
    }
}
