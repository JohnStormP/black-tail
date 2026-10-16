<?php

namespace App\Payment\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPaymentIdempotencyKeyException extends DomainException
{
    public static function empty(): self
    {
        return new self('Payment idempotency key cannot be empty');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self('Payment idempotency key cannot be longer than '.$maxLength.' characters');
    }
}
