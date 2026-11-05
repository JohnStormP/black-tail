<?php

namespace App\Tests\Identity\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Player\Domain\Entity\Player;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class MoneyHttpAccessTest extends WebTestCase
{
    private const string TENANT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string SUPPORT_ID = '01ARZ3NDEKTSV4RRFFQ69G5F31';
    private const string ANALYST_ID = '01ARZ3NDEKTSV4RRFFQ69G5F32';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string BET_ID = '01BX5ZZKBKACTAV9WEVGEMMVB01';

    private function seedTenantAndRoles(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_ID, 'Casino Money Roles'));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);

        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
            self::SUPPORT_ID,
            'support-money@example.com',
            'secret-password',
            'support',
            self::TENANT_ID,
        ));
        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
            self::ANALYST_ID,
            'analyst-money@example.com',
            'secret-password',
            'analyst',
            self::TENANT_ID,
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

    /**
     * @return list<string>
     */
    private function moneyEndpoints(): array
    {
        return [
            '/api/payments/deposits',
            '/api/payments/withdrawals',
            '/api/bets/'.self::BET_ID.'/settle',
            '/api/players/'.self::PLAYER_ID.'/block',
        ];
    }

    private function assertMoneyEndpointsForbiddenFor(string $email): void
    {
        $client = static::createClient();
        $this->seedTenantAndRoles();
        $this->login($client, $email);

        foreach ($this->moneyEndpoints() as $path) {
            $client->request(
                'POST',
                $path,
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ],
                content: '{}',
            );
            self::assertResponseStatusCodeSame(403, $path.' for '.$email);
        }
    }

    public function testSupportForbiddenOnMoneyEndpoints(): void
    {
        $this->assertMoneyEndpointsForbiddenFor('support-money@example.com');
    }

    public function testAnalystForbiddenOnMoneyEndpoints(): void
    {
        $this->assertMoneyEndpointsForbiddenFor('analyst-money@example.com');
    }
}
