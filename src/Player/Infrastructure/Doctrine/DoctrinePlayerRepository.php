<?php

namespace App\Player\Infrastructure\Doctrine;

use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PlayerRepositoryInterface::class, public: true)]
final class DoctrinePlayerRepository implements PlayerRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Player $player): void
    {
        $this->entityManager->persist($player);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(PlayerId $id): ?Player
    {
        return $this->entityManager->find(Player::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(PlayerId $id): Player
    {
        $player = $this->findById($id);

        if (null === $player) {
            throw PlayerNotFoundException::withId($id);
        }

        return $player;
    }
}
