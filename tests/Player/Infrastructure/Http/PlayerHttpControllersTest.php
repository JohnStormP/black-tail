<?php

namespace App\Tests\Player\Infrastructure\Http;

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

final class PlayerHttpControllersTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_A = '01JC9K2M5N7P8Q3R4S6T7V8WF1';
    private const string PLAYER_NEW = '01JC9K2M5N7P8Q3R4S6T7V8WF2';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string SUPER_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';

    private function seed(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $registerTenant = new RegisterTenantHandler($tenants);
        $registerTenant(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        $registerTenant(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $registerPlayer = new RegisterPlayerHandler($players);
        $registerPlayer(new RegisterPlayerCommand(
            self::PLAYER_A,
            self::TENANT_A,
            'player-a@example.com',
        ));

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

    public function testListPlayersRequiresTenantAndScopesAccess(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request('GET', '/api/players', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(400);

        $client->request(
            'GET',
            '/api/players?tenantId='.self::TENANT_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertSame(self::PLAYER_A, $payload[0]['id']);

        $client->request(
            'GET',
            '/api/players?tenantId='.self::TENANT_B,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(403);

        $this->login($client, 'super@example.com');
        $client->request(
            'GET',
            '/api/players?tenantId='.self::TENANT_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertSame(self::PLAYER_A, $payload[0]['id']);
    }

    public function testGetRegisterAndBlockPlayer(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'GET',
            '/api/players/'.self::PLAYER_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('player-a@example.com', $payload['email']);
        self::assertSame('active', $payload['status']);

        $foreignBody = json_encode([
            'playerId' => self::PLAYER_NEW,
            'tenantId' => self::TENANT_B,
            'email' => 'x@y.com',
        ], \JSON_THROW_ON_ERROR);
        $client->request(
            'POST',
            '/api/players',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $foreignBody,
        );
        $this->assertResponseStatusCodeSame(403);

        $createBody = json_encode([
            'playerId' => self::PLAYER_NEW,
            'tenantId' => self::TENANT_A,
            'email' => 'new@example.com',
        ], \JSON_THROW_ON_ERROR);
        $client->request(
            'POST',
            '/api/players',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $createBody,
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::PLAYER_NEW, $payload['id']);
        self::assertSame('active', $payload['status']);

        $client->request(
            'POST',
            '/api/players',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $createBody,
        );
        $this->assertResponseStatusCodeSame(409);

        $client->request(
            'POST',
            '/api/players/'.self::PLAYER_NEW.'/block',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('blocked', $payload['status']);

        $this->login($client, 'super@example.com');
        $client->request(
            'GET',
            '/api/players/'.self::PLAYER_NEW,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('blocked', $payload['status']);
    }
}
