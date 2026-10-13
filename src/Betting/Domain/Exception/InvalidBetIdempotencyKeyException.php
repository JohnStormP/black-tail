<?php

namespace App\Betting\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidBetIdempotencyKeyException extends DomainException
{
    public static function empty(): self
    {
        return new self('Bet idempotency key cannot be empty');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self('Bet idempotency key cannot be longer than '.$maxLength.' characters');
    }
}
