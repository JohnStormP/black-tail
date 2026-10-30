<?php

namespace App\Tests\Shared\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Application\Handler\RegisterPlayerHandler;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class TenantIsolationSmokeTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_B = '01JC9K2M5N7P8Q3R4S6T7V8WP1';
    private const string ADMIN_A = '01ARZ3NDEKTSV4RRFFQ69G5F01';

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
            self::ADMIN_A,
            'admin-a@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_A,
        ));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        new RegisterPlayerHandler($players)(new RegisterPlayerCommand(
            self::PLAYER_B,
            self::TENANT_B,
            'player-b@example.com',
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

    public function testTenantAdminCannotReadForeignPlayer(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);
        $client->request(
            'GET',
            '/api/players/'.self::PLAYER_B,
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );
        $content = $client->getResponse()->getContent() ?: '';
        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('player-b@example.com', $content);
        self::assertStringNotContainsString('Trace', $content);
        self::assertStringNotContainsString('#0 ', $content);
    }
}
