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
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class InitiateDepositControllerTest extends WebTestCase
{
    private const string TENANT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ADMIN_ID = '01ARZ3NDEKTSV4RRFFQ69G5F30';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string WALLET_ID = '01CXYZ00000000000000000001';

    private function seedAdmin(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.Payment::class)->execute();
        $em->createQuery('DELETE FROM '.Wallet::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
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
            '/api/payments/deposits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'paymentId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'playerId' => '01BX5ZZKBKACTAV9WEVGEMMVRY',
                'walletId' => '01CXYZ00000000000000000001',
                'amountMinor' => 500,
                'currency' => 'USD',
                'idempotencyKey' => 'deposit-http-001',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(401);
    }

    public function testInitiatesDepositAndReturnsJson(): void
    {
        $client = static::createClient();
        $this->seedAdmin();
        $this->login($client);
        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);

        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));
        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);

        $open = new OpenWalletHandler($wallets);
        ($open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        $client->request(
            'POST',
            '/api/payments/deposits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'paymentId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'playerId' => '01BX5ZZKBKACTAV9WEVGEMMVRY',
                'walletId' => '01CXYZ00000000000000000001',
                'amountMinor' => 500,
                'currency' => 'USD',
                'idempotencyKey' => 'deposit-http-001',
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('type', $payload);
        $this->assertSame('deposit', $payload['type']);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('status', $payload);
    }
}
