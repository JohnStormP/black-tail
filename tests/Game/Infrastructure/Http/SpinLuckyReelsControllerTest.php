<?php

namespace App\Tests\Game\Infrastructure\Http;

use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Application\Handler\RegisterGameHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SpinLuckyReelsControllerTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01JC9K2M5N7P8Q3R4S6T7V8WG1';
    private const string TENANT_GAME_ID = '01JC9K2M5N7P8Q3R4S6T7V8WH1';
    private const string PLAYER_ID = '01JC9K2M5N7P8Q3R4S6T7V8WP1';
    private const string ROUND_ID = '01JC9K2M5N7P8Q3R4S6T7V8WR1';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string WALLET_ID = '01JC9K2M5N7P8Q3R4S6T7V8WW1';
    private const string DEPOSIT_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8WD1';
    private const string RESERVE_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8WE1';
    private const string SETTLE_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8WF1';
    private const string PAYOUT_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8WG2';

    private function seed(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.GameRound::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
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
        new RegisterGameHandler($games)(new RegisterGameCommand(self::GAME_ID, 'lucky_reels', 'Lucky Reels'));

        $tenantGames = static::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);
        $tenantGames->save(TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_A),
            GameId::fromString(self::GAME_ID),
        ));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_A),
            PlayerEmail::fromString('player-a@example.com'),
        ));

        $wallets = static::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $ledger = static::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $transactions = static::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);
        new OpenWalletHandler($wallets)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        new DepositWalletHandler($wallets, $ledger, $transactions)(
            new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 5000)
        );

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $passwordHashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $passwordHashers);
        new RegisterUserHandler($users, $passwordHashers)(new RegisterUserCommand(
            self::ADMIN_A_ID,
            'admin-a@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_A,
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

    public function testSpinCreatesRoundAndIsIdempotent(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);
        $client->request(
            'POST',
            '/api/games/lucky-reels/spin',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'roundId' => self::ROUND_ID,
                'gameId' => self::GAME_ID,
                'playerId' => self::PLAYER_ID,
                'walletId' => self::WALLET_ID,
                'betMinor' => 100,
                'currency' => 'USD',
                'reserveEntryId' => self::RESERVE_ENTRY_ID,
                'settleEntryId' => self::SETTLE_ENTRY_ID,
                'payoutEntryId' => self::PAYOUT_ENTRY_ID,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('betMinor', $payload);
        $this->assertArrayHasKey('stops', $payload);
        $id = $payload['id'];
        $betMinor = $payload['betMinor'];
        $stops = $payload['stops'];

        $client->request(
            'POST',
            '/api/games/lucky-reels/spin',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'roundId' => self::ROUND_ID,
                'gameId' => self::GAME_ID,
                'playerId' => self::PLAYER_ID,
                'walletId' => self::WALLET_ID,
                'betMinor' => 100,
                'currency' => 'USD',
                'reserveEntryId' => self::RESERVE_ENTRY_ID,
                'settleEntryId' => self::SETTLE_ENTRY_ID,
                'payoutEntryId' => self::PAYOUT_ENTRY_ID,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('betMinor', $payload);
        $this->assertArrayHasKey('stops', $payload);
        $this->assertSame($id, $payload['id']);
        $this->assertSame($betMinor, $payload['betMinor']);
        $this->assertSame($stops, $payload['stops']);
    }

    public function testSpinForbiddenForOtherTenantPlayer(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);

        $otherPlayerId = '01JC9K2M5N7P8Q3R4S6T7V8WP2';
        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString($otherPlayerId),
            TenantId::fromString(self::TENANT_B),
            PlayerEmail::fromString('player-b@example.com'),
        ));

        $client->request(
            'POST',
            '/api/games/lucky-reels/spin',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'roundId' => self::ROUND_ID,
                'gameId' => self::GAME_ID,
                'playerId' => $otherPlayerId,
                'walletId' => self::WALLET_ID,
                'betMinor' => 100,
                'currency' => 'USD',
                'reserveEntryId' => self::RESERVE_ENTRY_ID,
                'settleEntryId' => self::SETTLE_ENTRY_ID,
                'payoutEntryId' => self::PAYOUT_ENTRY_ID,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSpinRejectsInvalidBetWith422(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);

        $client->request(
            'POST',
            '/api/games/lucky-reels/spin',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'roundId' => self::ROUND_ID,
                'gameId' => self::GAME_ID,
                'playerId' => self::PLAYER_ID,
                'walletId' => self::WALLET_ID,
                'betMinor' => 1,
                'currency' => 'USD',
                'reserveEntryId' => self::RESERVE_ENTRY_ID,
                'settleEntryId' => self::SETTLE_ENTRY_ID,
                'payoutEntryId' => self::PAYOUT_ENTRY_ID,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(422);
    }
}
