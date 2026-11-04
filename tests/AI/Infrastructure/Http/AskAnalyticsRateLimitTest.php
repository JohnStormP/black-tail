<?php

namespace App\Tests\AI\Infrastructure\Http;

use App\AI\Infrastructure\Llm\FakeLlmClient;
use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Query\BetsByDayStat;
use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class AskAnalyticsRateLimitTest extends WebTestCase
{
    private const string TENANT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ADMIN_ID = '01ARZ3NDEKTSV4RRFFQ69G5F30';

    private function clearRateLimiterCache(): void
    {
        $cache = static::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(AdapterInterface::class, $cache);
        $cache->clear();
    }

    private function seedAdmin(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_ID, 'Casino A'));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
            self::ADMIN_ID,
            'admin-a@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_ID,
        ));
    }

    private function login(KernelBrowser $client): void
    {
        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => 'admin-a@example.com',
                'password' => 'secret-password',
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
    }

    public function testAskReturns429WhenRateLimitExceeded(): void
    {
        self::ensureKernelShutdown();
        putenv('AI_ASK_RATE_LIMIT=2');
        $_ENV['AI_ASK_RATE_LIMIT'] = '2';
        $_SERVER['AI_ASK_RATE_LIMIT'] = '2';

        try {
            $client = static::createClient();
            $this->clearRateLimiterCache();
            $this->seedAdmin();
            $this->login($client);
            $client->disableReboot();

            $stub = new class implements BetsAnalyticsQueryInterface {
                public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
                {
                    return [new BetsByDayStat('2026-10-20', 1, 10)];
                }

                public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
                {
                    return [];
                }
            };
            static::getContainer()->set(BetsAnalyticsQueryInterface::class, $stub);
            static::getContainer()->set(ClockInterface::class, new MockClock('2026-10-21 12:00:00', 'UTC'));
            $llm = static::getContainer()->get(FakeLlmClient::class);
            $this->assertInstanceOf(FakeLlmClient::class, $llm);
            $llm->respondWith('{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}');

            for ($i = 0; $i < 2; ++$i) {
                $client->request('POST', '/api/ai/ask', server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ], content: json_encode(['question' => 'How many bets last week?', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR));
                self::assertResponseStatusCodeSame(200);
                $llm->respondWith('{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}');
            }

            $client->request('POST', '/api/ai/ask', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], content: json_encode(['question' => 'How many bets last week?', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR));

            $this->assertResponseStatusCodeSame(429);
            $this->clearRateLimiterCache();
        } finally {
            putenv('AI_ASK_RATE_LIMIT=1000');
            $_ENV['AI_ASK_RATE_LIMIT'] = '1000';
            $_SERVER['AI_ASK_RATE_LIMIT'] = '1000';
            self::ensureKernelShutdown();
        }
    }
}
