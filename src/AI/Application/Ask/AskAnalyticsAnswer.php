<?php

namespace App\AI\Application\Ask;

use App\AI\Domain\ValueObject\AnalyticsIntent;

final readonly class AskAnalyticsAnswer
{
    /**
     * @param non-empty-string $text
     */
    public function __construct(
        public string $text,
        public AnalyticsIntent $intent,
    ) {
    }
}
