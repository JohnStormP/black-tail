<?php

namespace App\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Exception\GameNotFoundException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GameRepositoryInterface::class, public: true)]
final class DoctrineGameRepository implements GameRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Game $game): void
    {
        $this->entityManager->persist($game);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(GameId $id): ?Game
    {
        return $this->entityManager->find(Game::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(GameId $id): Game
    {
        $game = $this->findById($id);

        if (null === $game) {
            throw GameNotFoundException::withId($id);
        }

        return $game;
    }

    public function findByCode(GameCode $code): ?Game
    {
        return $this->entityManager->getRepository(Game::class)->findOneBy([
            'code' => $code->toString(),
        ]);
    }

    /**
     * @return list<Game>
     */
    public function findAll(): array
    {
        /** @var list<Game> $games */
        $games = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Game::class, 'g')
            ->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $games;
    }
}
