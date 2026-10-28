<?php

namespace App\Tests\Game\Infrastructure\Http;

use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
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

final class CrashPlayHttpControllersTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string ROUND_ID = '01JC9K2M5N7P8Q3R4S6T7V8CR1';
    private const string BET_ID = '01JC9K2M5N7P8Q3R4S6T7V8CB1';
    private const string PLAYER_ID = '01JC9K2M5N7P8Q3R4S6T7V8CP1';
    private const string PLAYER_B_ID = '01JC9K2M5N7P8Q3R4S6T7V8CP2';
    private const string WALLET_ID = '01JC9K2M5N7P8Q3R4S6T7V8CW1';
    private const string RESERVE_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8CE1';
    private const string SETTLE_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8CE2';
    private const string PAYOUT_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8CE3';
    private const string DEPOSIT_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8CE0';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string SUPER_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';

    private function stubCrashRealtime(): void
    {
        static::getContainer()->set(CrashRealtimePublisherInterface::class, new class implements CrashRealtimePublisherInterface {
            public function publishStarted(CrashRoundId $roundId): void
            {
            }

            public function publishTick(CrashRoundId $roundId, int $hundredths): void
            {
            }

            public function publishCrashed(CrashRoundId $roundId, int $crashPointHundredths): void
            {
            }
        });
    }

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.CrashBet::class)->execute();
        $em->createQuery('DELETE FROM '.CrashRound::class)->execute();
        $em->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $em->createQuery('DELETE FROM '.Wallet::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_A),
            PlayerEmail::fromString('crash-player@example.com'),
        ));

        $wallets = static::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $ledger = static::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $transactions = static::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);
        new OpenWalletHandler($wallets)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        new DepositWalletHandler($wallets, $ledger, $transactions)(new DepositWalletCommand(
            self::WALLET_ID,
            self::DEPOSIT_ENTRY_ID,
            10_000,
        ));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        $registerUser = new RegisterUserHandler($users, $hashers);
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

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(KernelBrowser $client): array
    {
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    public function testOpenStartPlaceCashOutFlow(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->stubCrashRealtime();
        $this->seed();
        $this->login($client, 'super@example.com');

        $client->request(
            'POST',
            '/api/crash/rounds',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['roundId' => self::ROUND_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = $this->jsonPayload($client);
        $this->assertSame('waiting', $payload['status']);
        $this->assertArrayNotHasKey('crashPointHundredths', $payload);

        $client->request(
            'POST',
            '/api/crash/rounds',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['roundId' => self::ROUND_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = $this->jsonPayload($client);
        $this->assertArrayNotHasKey('crashPointHundredths', $payload);

        $this->login($client, 'admin-a@example.com');
        $placeBody = json_encode([
            'betId' => self::BET_ID,
            'roundId' => self::ROUND_ID,
            'playerId' => self::PLAYER_ID,
            'walletId' => self::WALLET_ID,
            'reserveEntryId' => self::RESERVE_ENTRY_ID,
            'amountMinor' => 500,
            'currency' => 'USD',
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/crash/bets',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $placeBody,
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = $this->jsonPayload($client);
        $this->assertSame(self::BET_ID, $payload['id']);
        $this->assertSame('open', $payload['status']);

        $client->request(
            'POST',
            '/api/crash/bets',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $placeBody,
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = $this->jsonPayload($client);
        $this->assertSame(self::BET_ID, $payload['id']);

        $this->login($client, 'super@example.com');
        $client->request(
            'POST',
            '/api/crash/rounds/'.self::ROUND_ID.'/start',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = $this->jsonPayload($client);
        $this->assertSame('running', $payload['status']);
        $this->assertArrayNotHasKey('crashPointHundredths', $payload);

        $rounds = static::getContainer()->get(CrashRoundRepositoryInterface::class);
        self::assertInstanceOf(CrashRoundRepositoryInterface::class, $rounds);
        $round = $rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $cashOutAt = max(100, min(150, $round->crashPoint()->toHundredths() - 1));

        $this->login($client, 'admin-a@example.com');
        $cashOutBody = json_encode([
            'settleEntryId' => self::SETTLE_ENTRY_ID,
            'payoutEntryId' => self::PAYOUT_ENTRY_ID,
            'cashOutHundredths' => $cashOutAt,
        ], \JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/crash/bets/'.self::BET_ID.'/cash-out',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $cashOutBody,
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = $this->jsonPayload($client);
        $this->assertSame('cashed_out', $payload['status']);
        $this->assertNotNull($payload['payoutMinor']);

        $client->request(
            'POST',
            '/api/crash/bets/'.self::BET_ID.'/cash-out',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $cashOutBody,
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = $this->jsonPayload($client);
        $this->assertSame('cashed_out', $payload['status']);
    }

    public function testPlaceBetForbiddenForOtherTenant(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->stubCrashRealtime();
        $this->seed();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_B_ID),
            TenantId::fromString(self::TENANT_B),
            PlayerEmail::fromString('crash-player-b@example.com'),
        ));

        $this->login($client, 'super@example.com');
        $client->request(
            'POST',
            '/api/crash/rounds',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['roundId' => self::ROUND_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(201);

        $this->login($client, 'admin-a@example.com');
        $client->request(
            'POST',
            '/api/crash/bets',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'betId' => self::BET_ID,
                'roundId' => self::ROUND_ID,
                'playerId' => self::PLAYER_B_ID,
                'walletId' => self::WALLET_ID,
                'reserveEntryId' => self::RESERVE_ENTRY_ID,
                'amountMinor' => 500,
                'currency' => 'USD',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testOpenRoundRequiresSuperAdmin(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/crash/rounds',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['roundId' => self::ROUND_ID], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(403);
    }
}
