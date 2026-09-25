<?php

namespace App\Shared\Infrastructure\Random;

use App\Shared\Domain\Exception\InvalidRandomRangeException;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class SeededRandomIntGenerator implements RandomIntGeneratorInterface
{
    private ?Randomizer $randomizer = null;

    public function __construct(
        private readonly int $seed,
    ) {
    }

    public function nextInt(int $min, int $max): int
    {
        if ($min > $max) {
            throw InvalidRandomRangeException::minGreaterThanMax($min, $max);
        }
        if (null === $this->randomizer) {
            $this->randomizer = new Randomizer(new Mt19937($this->seed));
        }

        return $this->randomizer->getInt($min, $max);
    }
}
