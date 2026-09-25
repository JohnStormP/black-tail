<?php

namespace App\Tests\Identity\Infrastructure\Security;

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

final class TenantAccessVoterTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string ADMIN_A_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string SUPER_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';

    private function seed(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $registerTenant = new RegisterTenantHandler($tenants);
        $registerTenant(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        $registerTenant(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

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

    public function testTenantAdminCanReadOwnTenantOnly(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request('GET', '/api/tenants/'.self::TENANT_A, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::TENANT_A, $payload['id']);
        $this->assertSame('Casino A', $payload['name']);

        $client->request('GET', '/api/tenants/'.self::TENANT_B, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanReadAnyTenant(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'super@example.com');

        $client->request('GET', '/api/tenants/'.self::TENANT_B, server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::TENANT_B, $payload['id']);
        $this->assertSame('Casino B', $payload['name']);
    }
}
