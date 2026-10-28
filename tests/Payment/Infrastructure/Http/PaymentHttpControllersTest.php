<?php

namespace App\Tests\Payment\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Payment\Domain\Entity\Payment;
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

final class PaymentHttpControllersTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01JC9K2M5N7P8Q3R4S6T7V8PP1';
    private const string WALLET_ID = '01JC9K2M5N7P8Q3R4S6T7V8PW1';
    private const string PAYMENT_ID = '01JC9K2M5N7P8Q3R4S6T7V8PM1';
    private const string RESERVE_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8PE1';
    private const string RELEASE_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8PE2';
    private const string DEPOSIT_ENTRY_ID = '01JC9K2M5N7P8Q3R4S6T7V8PE0';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.Payment::class)->execute();
        $em->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $em->createQuery('DELETE FROM '.Wallet::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        new RegisterTenantHandler($tenants)(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_A),
            PlayerEmail::fromString('pay-player@example.com'),
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
            50_000,
        ));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
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

    /**
     * @return array{
     *     paymentId: string,
     *     playerId: string,
     *     walletId: string,
     *     amountMinor: int,
     *     currency: string,
     *     idempotencyKey: string,
     *     reserveEntryId: string,
     *     releaseEntryId: string,
     *     provider: string,
     * }
     */
    private function withdrawalBody(): array
    {
        return [
            'paymentId' => self::PAYMENT_ID,
            'playerId' => self::PLAYER_ID,
            'walletId' => self::WALLET_ID,
            'amountMinor' => 1000,
            'currency' => 'USD',
            'idempotencyKey' => 'wd-1',
            'reserveEntryId' => self::RESERVE_ENTRY_ID,
            'releaseEntryId' => self::RELEASE_ENTRY_ID,
            'provider' => 'fake',
        ];
    }

    public function testWithdrawGetAndList(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);

        $body = json_encode($this->withdrawalBody(), \JSON_THROW_ON_ERROR);
        $client->request(
            'POST',
            '/api/payments/withdrawals',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $body,
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::PAYMENT_ID, $payload['id']);
        self::assertSame('withdrawal', $payload['type']);

        $client->request(
            'GET',
            '/api/payments/'.self::PAYMENT_ID,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::PAYMENT_ID, $payload['id']);

        $client->request(
            'GET',
            '/api/payments?tenantId='.self::TENANT_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $list = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($list);
        $ids = array_column($list, 'id');
        self::assertContains(self::PAYMENT_ID, $ids);

        $client->request(
            'GET',
            '/api/payments?tenantId='.self::TENANT_A.'&type=withdrawal',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $withdrawals = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($withdrawals);
        self::assertNotEmpty($withdrawals);
        foreach ($withdrawals as $item) {
            self::assertSame('withdrawal', $item['type']);
        }

        $client->request(
            'POST',
            '/api/payments/withdrawals',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $body,
        );
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::PAYMENT_ID, $payload['id']);
    }

    public function testListRequiresTenantId(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);

        $client->request(
            'GET',
            '/api/payments',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetForbiddenForOtherTenant(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client);

        $client->request(
            'GET',
            '/api/payments?tenantId='.self::TENANT_B,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(403);
    }
}
