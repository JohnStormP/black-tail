<?php

namespace App\AI\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AskAnalyticsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $question,
    ) {
    }
}
