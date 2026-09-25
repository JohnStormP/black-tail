<?php

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Infrastructure\Security\SecurityUserChecker;
use App\Identity\Infrastructure\Security\SecurityUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException as SecurityUserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class SecurityUserProviderTest extends KernelTestCase
{
    private const string USER_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private RegisterUserHandler $register;
    /** @var UserProviderInterface<SecurityUser> */
    private UserProviderInterface $provider;
    private SecurityUserChecker $checker;
    private UserRepositoryInterface $users;

    protected function setUp(): void
    {
        self::bootKernel();

        $users = self::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $this->users = $users;

        $passwordHashers = self::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $passwordHashers);

        $provider = self::getContainer()->get(UserProviderInterface::class);
        self::assertInstanceOf(SecurityUserProvider::class, $provider);
        $this->provider = $provider;

        $checker = self::getContainer()->get(SecurityUserChecker::class);
        self::assertInstanceOf(SecurityUserChecker::class, $checker);
        $this->checker = $checker;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();

        $this->register = new RegisterUserHandler($this->users, $passwordHashers);
    }

    public function testLoadUserByEmail(): void
    {
        ($this->register)(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_ID,
        ));

        $securityUser = $this->provider->loadUserByIdentifier('admin@example.com');
        self::assertInstanceOf(SecurityUser::class, $securityUser);
        $this->assertSame('admin@example.com', $securityUser->getUserIdentifier());
        $this->assertSame(['ROLE_TENANT_ADMIN'], $securityUser->getRoles());
        $this->assertSame(self::USER_ID, $securityUser->userId());
        $this->assertSame(self::TENANT_ID, $securityUser->tenantId());
        $this->assertTrue($securityUser->isActive());
    }

    public function testLoadUnknownEmailFails(): void
    {
        $this->expectException(SecurityUserNotFoundException::class);

        $this->provider->loadUserByIdentifier('missing@example.com');
    }

    public function testCheckerRejectsDisabledUser(): void
    {
        ($this->register)(new RegisterUserCommand(
            self::USER_ID,
            'admin@example.com',
            'secret-password',
            'super_admin',
        ));

        $domain = $this->users->getById(UserId::fromString(self::USER_ID));
        $domain->disable();
        $this->users->save($domain);

        $securityUser = $this->provider->loadUserByIdentifier('admin@example.com');

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->checker->checkPreAuth($securityUser);
    }
}
