<?php

namespace App\Shared\Infrastructure\Random;

use App\Shared\Domain\Exception\InvalidRandomRangeException;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;
use Random\RandomException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(RandomIntGeneratorInterface::class, public: true)]
final class PhpRandomIntGenerator implements RandomIntGeneratorInterface
{
    /**
     * @throws RandomException
     */
    public function nextInt(int $min, int $max): int
    {
        if ($min > $max) {
            throw InvalidRandomRangeException::minGreaterThanMax($min, $max);
        }

        return random_int($min, $max);
    }
}
