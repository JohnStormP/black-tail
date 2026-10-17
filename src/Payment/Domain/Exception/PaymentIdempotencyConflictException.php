<?php

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Shared\Domain\Exception\DomainException;

final class PaymentIdempotencyConflictException extends DomainException
{
    public static function forKey(PaymentIdempotencyKey $key): self
    {
        return new self('Payment with idempotency key "'.$key->toString().'" conflicts with the request');
    }
}
