<?php

namespace App\Tests\Analytics\Infrastructure\Http;

use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Handler\GetBetsByDayHandler;
use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Infrastructure\Http\GetBetsByDayController;
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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class GetBetsByDayControllerTest extends WebTestCase
{
    private const string TENANT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ADMIN_ID = '01ARZ3NDEKTSV4RRFFQ69G5F30';

    private function seedAdmin(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        (new RegisterTenantHandler($tenants))(new RegisterTenantCommand(self::TENANT_ID, 'Casino A'));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        (new RegisterUserHandler($users, $hashers))(new RegisterUserCommand(
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

    /**
     * @param list<BetsByDayStat> $stats
     */
    private function stubAnalytics(KernelBrowser $client, array $stats = []): void
    {
        $client->disableReboot();

        $spy = new class($stats) implements BetsAnalyticsQueryInterface {
            /** @param list<BetsByDayStat> $stats */
            public function __construct(private readonly array $stats)
            {
            }

            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                if ([] === $this->stats) {
                    Assert::fail('Unexpected ClickHouse call for this test case');
                }

                return $this->stats;
            }
        };

        static::getContainer()->set(
            GetBetsByDayController::class,
            new GetBetsByDayController(new GetBetsByDayHandler($spy)),
        );
    }

    public function testReturnsBetsByDayJson(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $this->stubAnalytics($client, [
            new BetsByDayStat('2026-10-20', 2, 150),
        ]);

        $client->request(
            'GET',
            '/api/analytics/bets-by-day?from=2026-10-20&to=2026-10-21',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $this->assertSame('2026-10-20', $payload['from']);
        $this->assertSame('2026-10-21', $payload['to']);
        $this->assertSame([
            [
                'day' => '2026-10-20',
                'betsCount' => 2,
                'amountMinor' => 150,
            ],
        ], $payload['days']);
    }

    public function testRejectsInvertedRangeWith400(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $this->stubAnalytics($client);
        $client->request(
            'GET',
            '/api/analytics/bets-by-day?from=2026-10-22&to=2026-10-20',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(400);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertIsString($payload['error']);
        $this->assertNotSame('', $payload['error']);
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/analytics/bets-by-day?from=2026-10-20&to=2026-10-21');
        $this->assertResponseStatusCodeSame(401);
    }
}
