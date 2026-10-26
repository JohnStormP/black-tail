<?php

namespace App\AI\Application\Ask;

final readonly class AskAnalyticsQuestionQuery
{
    /**
     * @param non-empty-string $question
     */
    public function __construct(
        public string $question,
    ) {
    }
}
