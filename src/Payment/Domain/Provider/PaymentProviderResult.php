<?php

namespace App\Payment\Domain\Provider;

final readonly class PaymentProviderResult
{
    public function __construct(
        public PaymentProviderInitiationStatus $status,
        public ?string $providerReference = null,
        public ?string $declineReason = null,
    ) {
    }

    public function isSubmitted(): bool
    {
        return PaymentProviderInitiationStatus::Submitted === $this->status;
    }

    public function isDeclined(): bool
    {
        return PaymentProviderInitiationStatus::Declined === $this->status;
    }
}
