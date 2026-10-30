<?php

namespace App\Tests\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\RequestId;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthAndReadyControllerTest extends WebTestCase
{
    public function testHealthIsPublicOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseFormatSame('json');
        self::assertSame(['status' => 'ok'], json_decode((string) $client->getResponse()->getContent(), true));
        self::assertTrue(RequestId::isValid((string) $client->getResponse()->headers->get(RequestId::HEADER)));
    }

    public function testReadyIsPublicWhenDatabaseIsUp(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ready');
        self::assertResponseStatusCodeSame(200);
        self::assertResponseFormatSame('json');
        self::assertSame(
            ['status' => 'ready'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
        self::assertTrue(RequestId::isValid((string) $client->getResponse()->headers->get(RequestId::HEADER)));
    }
}
