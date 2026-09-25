<?php

namespace App\Shared\Domain\Random;

use App\Shared\Domain\Exception\InvalidRandomRangeException;

interface RandomIntGeneratorInterface
{
    /**
     * @throws InvalidRandomRangeException
     */
    public function nextInt(int $min, int $max): int;
}
