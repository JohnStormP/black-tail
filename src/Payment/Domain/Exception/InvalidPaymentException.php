<?php

namespace App\Payment\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPaymentException extends DomainException
{
    public static function nonPositiveAmount(): self
    {
        return new self('Payment amount must be greater than zero');
    }

    public static function emptyProvider(): self
    {
        return new self('Payment provider cannot be empty');
    }

    public static function emptyFailureReason(): self
    {
        return new self('Payment failure reason cannot be empty');
    }
}
