<?php

namespace App\Payment\Infrastructure\Security;

use App\Payment\Domain\Exception\InvalidPaymentWebhookSignatureException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentWebhookSignatureVerifier
{
    public function __construct(
        #[Autowire('%env(PAYMENT_WEBHOOK_SECRET)%')]
        private readonly string $secret,
    ) {
    }

    public function assertValid(string $rawBody, ?string $signatureHeader): void
    {
        if ('' === trim($this->secret)) {
            throw InvalidPaymentWebhookSignatureException::secretNotConfigured();
        }

        if (null === $signatureHeader || '' === trim($signatureHeader)) {
            throw InvalidPaymentWebhookSignatureException::missingOrInvalid();
        }

        $provided = trim($signatureHeader);
        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $this->secret);
        if (!hash_equals($expected, $provided)) {
            throw InvalidPaymentWebhookSignatureException::missingOrInvalid();
        }
    }
}
