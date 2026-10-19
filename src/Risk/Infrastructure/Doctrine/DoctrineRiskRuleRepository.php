<?php

namespace App\Risk\Infrastructure\Doctrine;

use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\Exception\RiskRuleNotFoundException;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(RiskRuleRepositoryInterface::class, public: true)]
final class DoctrineRiskRuleRepository implements RiskRuleRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(RiskRule $rule): void
    {
        $this->entityManager->persist($rule);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(RiskRuleId $id): ?RiskRule
    {
        return $this->entityManager->find(RiskRule::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(RiskRuleId $id): RiskRule
    {
        $riskRule = $this->findById($id);

        if (null === $riskRule) {
            throw RiskRuleNotFoundException::withId($id);
        }

        return $riskRule;
    }

    public function findActiveByTenant(TenantId $tenantId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(RiskRule::class, 'r')
            ->where('r.tenantId = :tenantId')
            ->andWhere('r.status = :status')
            ->setParameter('tenantId', $tenantId->toString())
            ->setParameter('status', RiskRuleStatus::Active)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
