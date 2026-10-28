<?php

namespace App\Tests\Game\Infrastructure\Http;

use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Application\Handler\RegisterGameHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class GameHttpControllersTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_A = '01JC9K2M5N7P8Q3R4S6T7V8WG1';
    private const string GAME_NEW = '01JC9K2M5N7P8Q3R4S6T7V8WG2';
    private const string TENANT_GAME_NEW = '01JC9K2M5N7P8Q3R4S6T7V8WH1';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string SUPER_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';

    private function seed(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $registerTenant = new RegisterTenantHandler($tenants);
        $registerTenant(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        $registerTenant(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $games = static::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        $registerGame = new RegisterGameHandler($games);
        $registerGame(new RegisterGameCommand(self::GAME_A, 'lucky_reels', 'Lucky Reels'));

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

    public function testGameCatalogListGetAndRegister(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request('GET', '/api/games', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $ids = array_column($payload, 'id');
        $codes = array_column($payload, 'code');
        self::assertContains(self::GAME_A, $ids);
        self::assertContains('lucky_reels', $codes);

        $client->request(
            'GET',
            '/api/games/'.self::GAME_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Lucky Reels', $payload['name']);
        self::assertSame('lucky_reels', $payload['code']);

        $createBody = json_encode([
            'gameId' => self::GAME_NEW,
            'code' => 'crash',
            'name' => 'Crash',
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/games',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $createBody,
        );
        $this->assertResponseStatusCodeSame(403);

        $this->login($client, 'super@example.com');
        $client->request(
            'POST',
            '/api/games',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $createBody,
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::GAME_NEW, $payload['id']);
        self::assertSame('crash', $payload['code']);

        $client->request(
            'POST',
            '/api/games',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $createBody,
        );
        $this->assertResponseStatusCodeSame(409);
    }

    public function testTenantGamesAssignListEnableDisable(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request('GET', '/api/tenant-games', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(400);

        $client->request(
            'GET',
            '/api/tenant-games?tenantId='.self::TENANT_B,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(403);

        $assignBody = json_encode([
            'tenantGameId' => self::TENANT_GAME_NEW,
            'tenantId' => self::TENANT_A,
            'gameId' => self::GAME_A,
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/tenant-games',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $assignBody,
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::TENANT_GAME_NEW, $payload['id']);
        self::assertTrue($payload['enabled']);

        $client->request(
            'POST',
            '/api/tenant-games',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $assignBody,
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::TENANT_GAME_NEW, $payload['id']);

        $client->request(
            'GET',
            '/api/tenant-games?tenantId='.self::TENANT_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertSame(self::TENANT_GAME_NEW, $payload[0]['id']);

        $client->request(
            'POST',
            '/api/tenant-games/'.self::TENANT_GAME_NEW.'/disable',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($payload['enabled']);

        $client->request(
            'POST',
            '/api/tenant-games/'.self::TENANT_GAME_NEW.'/enable',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['enabled']);
    }
}
