<?php

namespace App\Bonus\Infrastructure\Doctrine;

use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\PlayerBonusNotFoundException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PlayerBonusRepositoryInterface::class, public: true)]
final class DoctrinePlayerBonusRepository implements PlayerBonusRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(PlayerBonus $bonus): void
    {
        $this->entityManager->persist($bonus);
        $this->entityManager->flush();
    }

    public function findById(PlayerBonusId $id): ?PlayerBonus
    {
        return $this->entityManager->find(PlayerBonus::class, $id->toString());
    }

    public function getById(PlayerBonusId $id): PlayerBonus
    {
        $bonus = $this->findById($id);
        if (null === $bonus) {
            throw PlayerBonusNotFoundException::withId($id);
        }

        return $bonus;
    }

    public function getByIdForUpdate(PlayerBonusId $id): PlayerBonus
    {
        $bonus = $this->entityManager->createQueryBuilder()
            ->select('pb')
            ->from(PlayerBonus::class, 'pb')
            ->where('pb.id = :id')
            ->setParameter('id', $id->toString())
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (null === $bonus) {
            throw PlayerBonusNotFoundException::withId($id);
        }

        return $bonus;
    }

    public function findByPlayerAndCampaign(PlayerId $playerId, BonusCampaignId $campaignId): ?PlayerBonus
    {
        return $this->entityManager->getRepository(PlayerBonus::class)->findOneBy([
            'playerId' => $playerId->toString(),
            'campaignId' => $campaignId->toString(),
        ]);
    }

    public function findOldestActiveByPlayer(PlayerId $playerId, Currency $currency): ?PlayerBonus
    {
        return $this->entityManager->createQueryBuilder()
            ->select('pb')
            ->from(PlayerBonus::class, 'pb')
            ->where('pb.playerId = :playerId')
            ->andWhere('pb.status = :status')
            ->andWhere('pb.currency = :currency')
            ->setParameter('playerId', $playerId->toString())
            ->setParameter('status', PlayerBonusStatus::Active)
            ->setParameter('currency', $currency->value)
            ->orderBy('pb.grantedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
