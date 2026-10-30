<?php

namespace App\Tests\Identity\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class LoginRateLimitTest extends WebTestCase
{
    private const string USER_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private function clearRateLimiterCache(): void
    {
        $cache = static::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(AdapterInterface::class, $cache);
        $cache->clear();
    }

    private function registerTenantAdmin(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $passwordHashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $passwordHashers);

        $register = new RegisterUserHandler($users, $passwordHashers);
        $register(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_ID,
        ));
    }

    public function testLoginReturns429AfterTooManyFailedAttempts(): void
    {
        $client = static::createClient();
        $this->clearRateLimiterCache();

        try {
            $this->registerTenantAdmin();
            $client->disableReboot();

            for ($i = 0; $i < 3; ++$i) {
                $client->request(
                    'POST',
                    '/api/login',
                    server: [
                        'CONTENT_TYPE' => 'application/json',
                        'HTTP_ACCEPT' => 'application/json',
                    ],
                    content: json_encode([
                        'email' => 'admin@example.com',
                        'password' => 'wrong-password',
                    ], \JSON_THROW_ON_ERROR),
                );
                $this->assertResponseStatusCodeSame(401);
            }

            $client->request(
                'POST',
                '/api/login',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ],
                content: json_encode([
                    'email' => 'admin@example.com',
                    'password' => 'wrong-password',
                ], \JSON_THROW_ON_ERROR),
            );

            $this->assertResponseStatusCodeSame(429);
            $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
            $this->assertSame('Too many login attempts', $payload['message']);
        } finally {
            $this->clearRateLimiterCache();
        }
    }
}
