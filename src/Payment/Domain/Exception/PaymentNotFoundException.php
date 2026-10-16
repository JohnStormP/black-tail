<?php

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\Exception\DomainException;

final class PaymentNotFoundException extends DomainException
{
    public static function withId(PaymentId $id): self
    {
        return new self('Payment with id "'.$id->toString().'" cannot be found');
    }
}
