<?php

namespace App\Tests\Shared\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Infrastructure\Http\HttpPayloadLimits;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class HttpRequestValidationTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string SUPER_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';
    private const string ULID_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string ULID_C = '01CXYZ00000000000000000001';
    private const string ULID_D = '01JC9K2M5N7P8Q3R4S6T7V8WG1';

    private function seed(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $passwordHashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $passwordHashers);
        $registerUser = new RegisterUserHandler($users, $passwordHashers);
        $registerUser(new RegisterUserCommand(
            self::ADMIN_A_ID,
            'admin-a@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_A,
        ));
        $registerUser(new RegisterUserCommand(
            self::SUPER_ID,
            'super@example.com',
            'secret-password',
            'super_admin',
        ));
    }

    private function login(KernelBrowser $client, string $email): void
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

    public function testRegisterTenantRejectsInvalidUlidWith422(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'super@example.com');

        $client->request(
            'POST',
            '/api/tenants',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'tenantId' => 'not-a-ulid',
                'name' => 'X',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterPlayerRejectsInvalidPlayerIdWith422(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/players',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'playerId' => 'bad',
                'tenantId' => self::TENANT_A,
                'email' => 'p@example.com',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testPlaceBetRejectsAmountOverLimitWith422(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/bets',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'betId' => self::TENANT_A,
                'playerId' => self::ULID_B,
                'gameId' => self::ULID_C,
                'roundId' => self::ULID_D,
                'amountMinor' => HttpPayloadLimits::MAX_AMOUNT_MINOR + 1,
                'currency' => 'USD',
                'idempotencyKey' => 'k1',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testInitiateDepositRejectsUnknownCurrencyWith422(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/payments/deposits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'paymentId' => self::TENANT_A,
                'playerId' => self::ULID_B,
                'walletId' => self::ULID_C,
                'amountMinor' => 100,
                'currency' => 'BTC',
                'idempotencyKey' => 'd1',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testPaymentWebhookRejectsInvalidLedgerEntryIdWith422(): void
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
                'providerReference' => 'ref-1',
                'outcome' => 'failed',
                'ledgerEntryId' => 'not-a-ulid',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateRiskRuleRejectsUnknownTypeWith422(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/risk-rules',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'ruleId' => self::ULID_B,
                'tenantId' => self::TENANT_A,
                'name' => 'r',
                'type' => 'nope',
                'threshold' => 1,
                'action' => 'block',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testHttpPayloadLimitsExposeExpectedCurrencyWhitelist(): void
    {
        self::assertSame(['USD', 'EUR', 'UAH'], HttpPayloadLimits::CURRENCIES);
        self::assertContains('max_bet', HttpPayloadLimits::RISK_RULE_TYPES);
        self::assertContains('block', HttpPayloadLimits::RISK_RULE_ACTIONS);
        self::assertGreaterThan(0, HttpPayloadLimits::MAX_AMOUNT_MINOR);
    }
}
