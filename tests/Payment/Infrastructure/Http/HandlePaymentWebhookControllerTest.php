<?php

namespace App\Tests\Payment\Infrastructure\Http;

use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HandlePaymentWebhookControllerTest extends WebTestCase
{
    private const string LEDGER_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ88G5F99';

    public function testWebhookRejectsMissingSignature(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/payments/webhooks/fake',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'providerReference' => 'unknown-ref-001',
                'outcome' => 'completed',
                'ledgerEntryId' => self::LEDGER_ENTRY_ID,
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(401);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsString($payload['error']);
    }

    public function testWebhookRejectsInvalidSignature(): void
    {
        $content = json_encode([
            'providerReference' => 'unknown-ref-001',
            'outcome' => 'completed',
            'ledgerEntryId' => self::LEDGER_ENTRY_ID,
        ], \JSON_THROW_ON_ERROR);

        $client = static::createClient();
        $client->request(
            'POST',
            '/api/payments/webhooks/fake',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => 'deadbeef',
            ],
            content: $content,
        );

        $this->assertResponseStatusCodeSame(401);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsString($payload['error']);
    }

    public function testWebhookIsPublicButReturns404WhenUnknownReference(): void
    {
        $content = json_encode([
            'providerReference' => 'unknown-ref-001',
            'outcome' => 'completed',
            'ledgerEntryId' => self::LEDGER_ENTRY_ID,
        ], \JSON_THROW_ON_ERROR);

        $secret = $_ENV['PAYMENT_WEBHOOK_SECRET'] ?? getenv('PAYMENT_WEBHOOK_SECRET');
        $this->assertIsString($secret);
        $this->assertNotSame('', $secret);

        $client = static::createClient();
        $client->request(
            'POST',
            '/api/payments/webhooks/fake',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => FakePaymentProvider::sign($content, $secret),
            ],
            content: $content,
        );

        $this->assertResponseStatusCodeSame(404);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsString($payload['error']);
    }
}
