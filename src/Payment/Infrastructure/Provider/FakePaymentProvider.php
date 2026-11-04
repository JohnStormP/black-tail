<?php

namespace App\Payment\Infrastructure\Provider;

use App\Payment\Domain\Provider\PaymentProviderInitiationStatus;
use App\Payment\Domain\Provider\PaymentProviderInterface;
use App\Payment\Domain\Provider\PaymentProviderRequest;
use App\Payment\Domain\Provider\PaymentProviderResult;

final class FakePaymentProvider implements PaymentProviderInterface
{
    public const string CODE = 'fake';

    public function supports(string $provider): bool
    {
        return self::CODE === strtolower(trim($provider));
    }

    public function initiate(PaymentProviderRequest $request): PaymentProviderResult
    {
        $providerReference = 'fake-'.$request->paymentId->toString();

        return new PaymentProviderResult(PaymentProviderInitiationStatus::Submitted, $providerReference);
    }

    public static function sign(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }
}
