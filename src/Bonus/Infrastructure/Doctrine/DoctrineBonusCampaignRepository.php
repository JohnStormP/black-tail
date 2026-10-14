<?php

namespace App\Bonus\Infrastructure\Doctrine;

use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Exception\BonusCampaignNotFoundException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(BonusCampaignRepositoryInterface::class, public: true)]
final class DoctrineBonusCampaignRepository implements BonusCampaignRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(BonusCampaign $campaign): void
    {
        $this->entityManager->persist($campaign);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(BonusCampaignId $id): ?BonusCampaign
    {
        return $this->entityManager->find(BonusCampaign::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(BonusCampaignId $id): BonusCampaign
    {
        $campaign = $this->findById($id);
        if (null === $campaign) {
            throw BonusCampaignNotFoundException::withId($id);
        }

        return $campaign;
    }
}
