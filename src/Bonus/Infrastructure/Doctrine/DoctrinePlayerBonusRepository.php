<?php

namespace App\Bonus\Infrastructure\Doctrine;

use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\PlayerBonusNotFoundException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Player\Domain\ValueObject\PlayerId;
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

    public function findByPlayerAndCampaign(PlayerId $playerId, BonusCampaignId $campaignId): ?PlayerBonus
    {
        return $this->entityManager->getRepository(PlayerBonus::class)->findOneBy([
            'playerId' => $playerId->toString(),
            'campaignId' => $campaignId->toString(),
        ]);
    }
}
