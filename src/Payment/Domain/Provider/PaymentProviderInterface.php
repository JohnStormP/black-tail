<?php

namespace App\Payment\Domain\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.payment_provider')]
interface PaymentProviderInterface
{
    public function supports(string $provider): bool;

    public function initiate(PaymentProviderRequest $request): PaymentProviderResult;
}
