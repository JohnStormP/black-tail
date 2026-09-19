<?php

namespace App\Tests\Tenant\Infrastructure\Doctrine;

use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTenantRepositoryTest extends KernelTestCase
{
    private TenantRepositoryInterface $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $this->entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $id = TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $tenant = Tenant::register($id, 'Casino Alpha');

        $this->repository->save($tenant);

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($id));
        $this->assertSame('Casino Alpha', $found->name());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(TenantNotFoundException::class);

        $this->repository->getById(TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
