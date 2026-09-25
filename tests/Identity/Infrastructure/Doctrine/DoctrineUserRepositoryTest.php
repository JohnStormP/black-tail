<?php

namespace App\Tests\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserEmail;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Domain\ValueObject\UserRole;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();
    }

    public function testSaveFindByIdAndFindByEmail(): void
    {
        $userId = UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $tenantId = TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $email = UserEmail::fromString('admin@example.com');

        $user = User::register(
            $userId,
            $email,
            'hashed-password',
            UserRole::TenantAdmin,
            $tenantId,
        );

        $this->repository->save($user);

        $byId = $this->repository->findById($userId);
        $this->assertNotNull($byId);
        $this->assertTrue($byId->id()->equals($userId));
        $this->assertSame('admin@example.com', $byId->email()->toString());
        $this->assertTrue($byId->tenantId()?->equals($tenantId));

        $byEmail = $this->repository->findByEmail($email);
        $this->assertNotNull($byEmail);
        $this->assertTrue($byEmail->id()->equals($userId));
    }

    public function testSaveSuperAdminWithoutTenant(): void
    {
        $userId = UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->repository->save(User::register(
            $userId,
            UserEmail::fromString('super@example.com'),
            'hashed-password',
            UserRole::SuperAdmin,
        ));

        $found = $this->repository->getById($userId);
        $this->assertNull($found->tenantId());
        $this->assertSame(UserRole::SuperAdmin, $found->role());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->getById(UserId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
