<?php

namespace App\Payment\Domain\Provider;

use App\Payment\Domain\Exception\PaymentProviderNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class PaymentProviderResolver
{
    /**
     * @param iterable<PaymentProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.payment_provider')]
        private readonly iterable $providers,
    ) {
    }

    public function resolve(string $provider): PaymentProviderInterface
    {
        foreach ($this->providers as $candidate) {
            if ($candidate->supports($provider)) {
                return $candidate;
            }
        }
        throw PaymentProviderNotFoundException::forProvider($provider);
    }
}
