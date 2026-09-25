<?php

namespace App\Tests\Wallet\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Application\Handler\RegisterPlayerHandler;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
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

final class GetWalletControllerTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_A = '01ARZ3NDEKTSV4RRFFQ69G5F10';
    private const string WALLET_A = '01ARZ3NDEKTSV4RRFFQ69G5F20';
    private const string ADMIN_A = '01ARZ3NDEKTSV4RRFFQ69G5F30';
    private const string ADMIN_B = '01ARZ3NDEKTSV4RRFFQ69G5F31';
    private const string DEPOSIT_ENTRY = '01ARZ3NDEKTSV4RRFFQ69G5F40';

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $em->createQuery('DELETE FROM '.Wallet::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $registerTenant = new RegisterTenantHandler($tenants);
        $registerTenant(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        $registerTenant(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        (new RegisterPlayerHandler($players))(new RegisterPlayerCommand(
            self::PLAYER_A,
            self::TENANT_A,
            'player-a@example.com',
        ));

        $wallets = static::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        (new OpenWalletHandler($wallets))(new OpenWalletCommand(self::WALLET_A, self::PLAYER_A, 'USD'));

        $ledger = static::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $tx = static::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $tx);
        (new DepositWalletHandler($wallets, $ledger, $tx))(new DepositWalletCommand(
            self::WALLET_A,
            self::DEPOSIT_ENTRY,
            1500,
        ));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        $registerUser = new RegisterUserHandler($users, $hashers);
        $registerUser(new RegisterUserCommand(
            self::ADMIN_A,
            'admin-a@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_A,
        ));
        $registerUser(new RegisterUserCommand(
            self::ADMIN_B,
            'admin-b@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_B,
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

    public function testTenantAdminCanReadOwnPlayerWallet(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request('GET', '/api/wallets/'.self::WALLET_A, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::WALLET_A, $payload['id']);
        $this->assertSame(self::PLAYER_A, $payload['playerId']);
        $this->assertSame('USD', $payload['currency']);
        $this->assertSame(1500, $payload['available']);
        $this->assertSame(0, $payload['reserved']);
        $this->assertSame('active', $payload['status']);
    }

    public function testForeignTenantAdminIsDenied(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-b@example.com');

        $client->request('GET', '/api/wallets/'.self::WALLET_A, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(403);
    }
}
