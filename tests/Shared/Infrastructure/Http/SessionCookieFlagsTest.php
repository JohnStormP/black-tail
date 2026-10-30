<?php

namespace App\Tests\Shared\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SessionCookieFlagsTest extends WebTestCase
{
    private const string USER_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    public function testSessionCookieFlagsAreHardened(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.User::class)->execute();

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        new RegisterUserHandler($users, $hashers)(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_ID,
        ));

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
        self::assertResponseStatusCodeSame(200);
        $setCookie = $client->getResponse()->headers->get('Set-Cookie');
        self::assertNotNull($setCookie);
        self::assertStringContainsStringIgnoringCase('httponly', $setCookie);
        self::assertStringContainsStringIgnoringCase('samesite=lax', $setCookie);
    }
}
