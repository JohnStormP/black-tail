<?php

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\Exception\DomainException;

final class InvalidPaymentTransitionException extends DomainException
{
    public static function cannotTransition(PaymentStatus $from, PaymentStatus $to): self
    {
        return new self('Cannot transition payment from "'.$from->value.'" to "'.$to->value.'"');
    }
}
