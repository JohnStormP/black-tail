<?php

namespace App\Tests\Identity\Application\Handler;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\InvalidUserException;
use App\Identity\Domain\Exception\UserAlreadyExistsException;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Domain\ValueObject\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class RegisterUserHandlerTest extends KernelTestCase
{
    private const string USER_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private RegisterUserHandler $handler;
    private UserRepositoryInterface $users;
    private PasswordHasherFactoryInterface $passwordHashers;

    protected function setUp(): void
    {
        self::bootKernel();

        $users = self::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $this->users = $users;

        $passwordHashers = self::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $passwordHashers);
        $this->passwordHashers = $passwordHashers;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();

        $this->handler = new RegisterUserHandler($this->users, $this->passwordHashers);
    }

    public function testRegistersTenantAdminWithHashedPassword(): void
    {
        ($this->handler)(new RegisterUserCommand(
            self::USER_ID,
            'Admin@Example.COM',
            'secret-password',
            'tenant_admin',
            self::TENANT_ID,
        ));

        $user = $this->users->getById(UserId::fromString(self::USER_ID));
        $this->assertSame('admin@example.com', $user->email()->toString());
        $this->assertSame(UserRole::TenantAdmin, $user->role());
        $this->assertSame(self::TENANT_ID, $user->tenantId()?->toString());
        $this->assertNotSame('secret-password', $user->passwordHash());

        $hasher = $this->passwordHashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
        $this->assertTrue($hasher->verify($user->passwordHash(), 'secret-password'));
    }

    public function testRegistersSuperAdminWithoutTenant(): void
    {
        ($this->handler)(new RegisterUserCommand(
            self::USER_ID,
            'super@example.com',
            'secret-password',
            'super_admin',
        ));

        $user = $this->users->getById(UserId::fromString(self::USER_ID));
        $this->assertSame(UserRole::SuperAdmin, $user->role());
        $this->assertNull($user->tenantId());
    }

    public function testRejectsEmptyPassword(): void
    {
        $this->expectException(InvalidUserException::class);

        ($this->handler)(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            '   ',
            'super_admin',
        ));
    }

    public function testRejectsDuplicateEmail(): void
    {
        ($this->handler)(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            'secret-password',
            'super_admin',
        ));

        $this->expectException(UserAlreadyExistsException::class);

        ($this->handler)(new RegisterUserCommand(
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'admin@example.com',
            'other-password',
            'super_admin',
        ));
    }
}
