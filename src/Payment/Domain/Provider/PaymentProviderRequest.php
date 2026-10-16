<?php

namespace App\Payment\Domain\Provider;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Wallet\Domain\ValueObject\Money;

final readonly class PaymentProviderRequest
{
    public function __construct(
        public PaymentId $paymentId,
        public PaymentType $type,
        public Money $amount,
        public PaymentIdempotencyKey $idempotencyKey,
    ) {
    }
}
