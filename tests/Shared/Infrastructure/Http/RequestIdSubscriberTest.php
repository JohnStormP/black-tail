<?php

namespace App\Tests\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\RequestId;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RequestIdSubscriberTest extends WebTestCase
{
    public function testHealthResponseIncludesGeneratedRequestId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        $requestId = $client->getResponse()->headers->get(RequestId::HEADER);
        self::assertNotNull($requestId);
        self::assertTrue(RequestId::isValid($requestId));
    }

    public function testReadyEchoesIncomingRequestId(): void
    {
        $client = static::createClient();
        $incoming = 'client-req-'.bin2hex(random_bytes(8));
        $client->request('GET', '/ready', server: [
            'HTTP_X_REQUEST_ID' => $incoming,
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($incoming, $client->getResponse()->headers->get(RequestId::HEADER));
    }

    public function testIgnoresInvalidIncomingRequestId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health', server: [
            'HTTP_X_REQUEST_ID' => "bad\nid",
        ]);

        self::assertResponseStatusCodeSame(200);
        $requestId = $client->getResponse()->headers->get(RequestId::HEADER);
        self::assertNotNull($requestId);
        self::assertTrue(RequestId::isValid($requestId));
        self::assertStringNotContainsString("\n", $requestId);
    }
}
