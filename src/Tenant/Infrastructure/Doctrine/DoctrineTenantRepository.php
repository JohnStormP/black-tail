<?php

namespace App\Tenant\Infrastructure\Doctrine;

use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantRepositoryInterface::class, public: true)]
final class DoctrineTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Tenant $tenant): void
    {
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(TenantId $id): ?Tenant
    {
        return $this->entityManager->find(Tenant::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(TenantId $id): Tenant
    {
        $tenant = $this->findById($id);
        if (null === $tenant) {
            throw TenantNotFoundException::withId($id);
        }

        return $tenant;
    }

    /**
     * @return list<Tenant>
     */
    public function findAll(): array
    {
        /** @var list<Tenant> $tenants */
        $tenants = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tenant::class, 't')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $tenants;
    }
}
