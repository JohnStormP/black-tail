<?php

namespace App\Payment\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class PaymentProviderNotFoundException extends DomainException
{
    public static function forProvider(string $provider): self
    {
        return new self('No payment provider registered for "'.$provider.'"');
    }
}
