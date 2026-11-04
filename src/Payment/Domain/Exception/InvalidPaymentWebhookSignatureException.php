<?php

namespace App\Payment\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPaymentWebhookSignatureException extends DomainException
{
    public static function missingOrInvalid(): self
    {
        return new self('Invalid payment webhook signature provided');
    }

    public static function secretNotConfigured(): self
    {
        return new self('Payment webhook secret is not configured');
    }
}
