<?php

namespace App\Tests\Payment\Domain\Provider;

use App\Payment\Domain\Exception\PaymentProviderNotFoundException;
use App\Payment\Domain\Provider\PaymentProviderRequest;
use App\Payment\Domain\Provider\PaymentProviderResolver;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PaymentProviderResolverTest extends TestCase
{
    public function testResolvesFakeProvider(): void
    {
        $resolver = new PaymentProviderResolver([
            new FakePaymentProvider(),
        ]);

        $provider = $resolver->resolve('fake');

        $this->assertInstanceOf(FakePaymentProvider::class, $provider);
        $this->assertTrue($provider->supports('fake'));
    }

    public function testResolveIsCaseInsensitiveViaSupports(): void
    {
        $resolver = new PaymentProviderResolver([
            new FakePaymentProvider(),
        ]);

        $provider = $resolver->resolve(' FAKE ');

        $this->assertInstanceOf(FakePaymentProvider::class, $provider);
    }

    public function testThrowsWhenNoProviderSupportsCode(): void
    {
        $resolver = new PaymentProviderResolver([
            new FakePaymentProvider(),
        ]);

        $this->expectException(PaymentProviderNotFoundException::class);

        $resolver->resolve('stripe');
    }

    public function testThrowsWhenProviderListEmpty(): void
    {
        $resolver = new PaymentProviderResolver([]);

        $this->expectException(PaymentProviderNotFoundException::class);

        $resolver->resolve('fake');
    }

    public function testFakeInitiateReturnsSubmittedWithDeterministicReference(): void
    {
        $paymentId = PaymentId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $provider = new FakePaymentProvider();

        $result = $provider->initiate(new PaymentProviderRequest(
            $paymentId,
            PaymentType::Deposit,
            Money::of(1000, Currency::Usd),
            PaymentIdempotencyKey::fromString('dep-key-1'),
        ));

        $this->assertTrue($result->isSubmitted());
        $this->assertFalse($result->isDeclined());
        $this->assertSame('fake-01ARZ3NDEKTSV4RRFFQ69G5FAV', $result->providerReference);
        $this->assertNull($result->declineReason);
    }
}
