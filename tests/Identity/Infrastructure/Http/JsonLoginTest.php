<?php

namespace App\Tests\Identity\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class JsonLoginTest extends WebTestCase
{
    private const string USER_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private function registerTenantAdmin(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $passwordHashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $passwordHashers);

        $register = new RegisterUserHandler($users, $passwordHashers);
        $register(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_ID,
        ));
    }

    public function testLoginSuccessThenMe(): void
    {
        $client = static::createClient();
        $this->registerTenantAdmin();

        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => 'admin@example.com',
                'password' => 'secret-password',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::USER_ID, $payload['id']);
        $this->assertSame('admin@example.com', $payload['email']);
        $this->assertSame(['ROLE_TENANT_ADMIN'], $payload['roles']);
        $this->assertSame(self::TENANT_ID, $payload['tenantId']);

        $client->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(200);
        $me = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::USER_ID, $me['id']);
        $this->assertSame('admin@example.com', $me['email']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $client = static::createClient();
        $this->registerTenantAdmin();

        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(401);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('Invalid credentials', $payload['message']);
    }

    public function testLoginFailsWhenDisabled(): void
    {
        $client = static::createClient();
        $this->registerTenantAdmin();

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $user = $users->getById(UserId::fromString(self::USER_ID));
        $user->disable();
        $users->save($user);

        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => 'admin@example.com',
                'password' => 'secret-password',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLogoutClearsSession(): void
    {
        $client = static::createClient();
        $this->registerTenantAdmin();

        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => 'admin@example.com',
                'password' => 'secret-password',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(200);
        $client->request(
            'POST',
            '/api/logout',
        );
        $this->assertResponseStatusCodeSame(204);

        $client->request(
            'GET',
            '/api/me',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );
        $this->assertResponseStatusCodeSame(401);
    }
}
