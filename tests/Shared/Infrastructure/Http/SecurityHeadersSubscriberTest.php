<?php

namespace App\Tests\Shared\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersSubscriberTest extends WebTestCase
{
    public function testHealthResponseHasSecurityHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);

        $headers = $client->getResponse()->headers;
        $permissionsPolicy = $headers->get('Permissions-Policy');
        self::assertNotNull($permissionsPolicy);
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('no-referrer', $headers->get('Referrer-Policy'));
        self::assertStringContainsString('camera=()', $permissionsPolicy);
        self::assertStringContainsString('microphone=()', $permissionsPolicy);
        self::assertStringContainsString('geolocation=()', $permissionsPolicy);
    }
}
