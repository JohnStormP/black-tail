<?php

namespace App\Tests\Payment\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HandlePaymentWebhookControllerTest extends WebTestCase
{
    private const string LEDGER_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ88G5F99';

    public function testWebhookIsPublicButReturns404WhenUnknownReference(): void
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
        $this->assertResponseStatusCodeSame(404);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsString($payload['error']);
    }
}
