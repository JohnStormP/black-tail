<?php

namespace App\Tests\Betting\Infrastructure\Http;

use App\Betting\Domain\Entity\Bet;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
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
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class PlaceBetControllerTest extends WebTestCase
{
    private const string TENANT_ID = '01EXYZ00000000000000000003';
    private const string ADMIN_ID = '01ARZ3NDEKTSV4RRFFQ69G5F30';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';
    private const string TENANT_GAME_ID = '01FXYZ00000000000000000004';
    private const string BET_ID = '01GXYZ00000000000000000005';
    private const string ROUND_ID = '01DXYZ00000000000000000002';
    private const string IDEMPOTENCY_KEY = 'place-http-001';

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.Bet::class)->execute();
        $em->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.Game::class)->execute();
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

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        new RegisterPlayerHandler($players)(new RegisterPlayerCommand(
            self::PLAYER_ID,
            self::TENANT_ID,
            'player@example.com',
        ));

        $games = static::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        $games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));

        $tenantGames = static::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);
        $tenantGames->save(TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
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

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/bets',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'betId' => self::BET_ID,
                'playerId' => self::PLAYER_ID,
                'gameId' => self::GAME_ID,
                'roundId' => self::ROUND_ID,
                'amountMinor' => 100,
                'currency' => 'USD',
                'idempotencyKey' => self::IDEMPOTENCY_KEY,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(401);
    }

    public function testPlacesBetAndReturnsJson(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);

        $client->request(
            'POST',
            '/api/bets',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'betId' => self::BET_ID,
                'playerId' => self::PLAYER_ID,
                'gameId' => self::GAME_ID,
                'roundId' => self::ROUND_ID,
                'amountMinor' => 100,
                'currency' => 'USD',
                'idempotencyKey' => self::IDEMPOTENCY_KEY,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $this->assertSame(self::BET_ID, $payload['id']);
        $this->assertSame('pending', $payload['status']);
        $this->assertSame(self::PLAYER_ID, $payload['playerId']);
        $this->assertSame(100, $payload['amountMinor']);
        $this->assertSame('USD', $payload['currency']);
        $this->assertSame(self::IDEMPOTENCY_KEY, $payload['idempotencyKey']);
    }
}
