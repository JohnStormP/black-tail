<?php

namespace App\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Exception\GameRoundNotFoundException;
use App\Game\Domain\Repository\GameRoundRepositoryInterface;
use App\Game\Domain\ValueObject\GameRoundId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GameRoundRepositoryInterface::class, public: true)]
final class DoctrineGameRoundRepository implements GameRoundRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(GameRound $round): void
    {
        $this->entityManager->persist($round);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(GameRoundId $id): ?GameRound
    {
        return $this->entityManager->find(GameRound::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(GameRoundId $id): GameRound
    {
        $round = $this->findById($id);
        if (null === $round) {
            throw GameRoundNotFoundException::withId($id);
        }

        return $round;
    }
}
