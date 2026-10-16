<?php

namespace App\Payment\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPaymentIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Payment ID cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid Payment ID format provided "'.$value.'"');
    }
}
