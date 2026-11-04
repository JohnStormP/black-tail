<?php

namespace App\Tests\Payment\Infrastructure\Security;

use App\Payment\Domain\Exception\InvalidPaymentWebhookSignatureException;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Payment\Infrastructure\Security\PaymentWebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class PaymentWebhookSignatureVerifierTest extends TestCase
{
    private const string SECRET = 'test-payment-webhook-secret';
    private const string BODY = '{"providerReference":"fake-1","outcome":"completed"}';

    public function testAcceptsMatchingHexSignature(): void
    {
        $verifier = new PaymentWebhookSignatureVerifier(self::SECRET);
        $verifier->assertValid(self::BODY, FakePaymentProvider::sign(self::BODY, self::SECRET));
        $this->addToAssertionCount(1);
    }

    public function testAcceptsSha256PrefixedSignature(): void
    {
        $verifier = new PaymentWebhookSignatureVerifier(self::SECRET);
        $hex = FakePaymentProvider::sign(self::BODY, self::SECRET);
        $verifier->assertValid(self::BODY, 'sha256='.$hex);
        $this->addToAssertionCount(1);
    }

    public function testRejectsEmptySecret(): void
    {
        $verifier = new PaymentWebhookSignatureVerifier('   ');
        $this->expectException(InvalidPaymentWebhookSignatureException::class);
        $verifier->assertValid(self::BODY, FakePaymentProvider::sign(self::BODY, self::SECRET));
    }

    public function testRejectsMissingHeader(): void
    {
        $verifier = new PaymentWebhookSignatureVerifier(self::SECRET);
        $this->expectException(InvalidPaymentWebhookSignatureException::class);
        $verifier->assertValid(self::BODY, null);
    }

    public function testRejectsWrongSignature(): void
    {
        $verifier = new PaymentWebhookSignatureVerifier(self::SECRET);
        $this->expectException(InvalidPaymentWebhookSignatureException::class);
        $verifier->assertValid(self::BODY, 'deadbeef');
    }
}
