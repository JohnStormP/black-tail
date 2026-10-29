<?php

namespace App\Analytics\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GgrByDayRequest
{
    /**
     * @param non-empty-string $from
     * @param non-empty-string $to
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public string $from,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public string $to,
    ) {
    }
}
