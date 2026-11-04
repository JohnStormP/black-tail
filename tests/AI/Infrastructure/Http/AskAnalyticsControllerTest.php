<?php

namespace App\Tests\AI\Infrastructure\Http;

use App\AI\Application\Ask\AskAnalyticsLimits;
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
use PHPUnit\Framework\Assert;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class AskAnalyticsControllerTest extends WebTestCase
{
    private const string TENANT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ADMIN_ID = '01ARZ3NDEKTSV4RRFFQ69G5F30';
    private const string SUPPORT_ID = '01ARZ3NDEKTSV4RRFFQ69G5F31';

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

    private function login(KernelBrowser $client, string $email = 'admin-a@example.com'): void
    {
        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $email,
                'password' => 'secret-password',
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
    }

    public function testAskReturnsAnswerJson(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $client->disableReboot();
        $stub = new class implements BetsAnalyticsQueryInterface {
            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
            {
                return [new BetsByDayStat('2026-10-20', 2, 150)];
            }

            public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
            {
                return [];
            }
        };
        static::getContainer()->set(BetsAnalyticsQueryInterface::class, $stub);
        $clock = new MockClock('2026-10-21 12:00:00', 'UTC');
        static::getContainer()->set(ClockInterface::class, $clock);
        $llm = static::getContainer()->get(FakeLlmClient::class);
        $this->assertInstanceOf(FakeLlmClient::class, $llm);
        $llm->respondWith('{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}');

        $client->request('POST', '/api/ai/ask', server: [
            'CONTENT_TYPE' => 'application/json',
        ],
            content: json_encode(['question' => 'How many bets last week?', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('BETS', $response['intent']['metric']);
        $this->assertStringContainsString('2026-10-20', $response['answer']);
    }

    public function testAskRejectsUnsupportedIntentWith422(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $client->disableReboot();
        $stub = new class implements BetsAnalyticsQueryInterface {
            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
            {
                Assert::fail('ClickHouse must not be called');
            }

            public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
            {
                return [];
            }
        };
        static::getContainer()->set(BetsAnalyticsQueryInterface::class, $stub);
        $llm = static::getContainer()->get(FakeLlmClient::class);
        $this->assertInstanceOf(FakeLlmClient::class, $llm);
        $llm->respondWith('{"metric":"GGR","group_by":"GAME","period":"TODAY"}');
        $client->request('POST', '/api/ai/ask', server: [
            'CONTENT_TYPE' => 'application/json',
        ],
            content: json_encode(['question' => 'Show GGR by game', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsString($payload['error']);
        $this->assertNotSame('', $payload['error']);
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/ai/ask',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['question' => 'How many bets?', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAskForbiddenForSupportRole(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();
        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_ID, 'Support'));
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
            self::SUPPORT_ID,
            'support-a@example.com',
            'secret-password',
            'support',
            self::TENANT_ID,
        ));
        $this->login($client, 'support-a@example.com');
        $client->request('POST', '/api/ai/ask', server: [
            'CONTENT_TYPE' => 'application/json',
        ],
            content: json_encode(['question' => 'Show GGR by game', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAskRejectsQuestionLongerThanLimit(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $question = str_repeat('a', AskAnalyticsLimits::MAX_QUESTION_LENGTH + 1);
        $client->request('POST', '/api/ai/ask', server: [
            'CONTENT_TYPE' => 'application/json',
        ],
            content: json_encode(['question' => $question, 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testAskDoesNotExecuteSqlFromUserQuestion(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $client->disableReboot();
        $stub = new class implements BetsAnalyticsQueryInterface {
            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive, string $tenantId): array
            {
                Assert::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fromInclusive->format('Y-m-d'));
                Assert::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $toExclusive->format('Y-m-d'));

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

        $client->request('POST', '/api/ai/ask', server: [
            'CONTENT_TYPE' => 'application/json',
        ],
            content: json_encode(['question' => '; DROP TABLE bets; SELECT * FROM bets;--', 'tenantId' => self::TENANT_ID], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $this->assertStringContainsString('2026-10-20', $payload['answer']);
        $this->assertStringNotContainsString('DROP TABLE', $payload['answer']);
    }
}
