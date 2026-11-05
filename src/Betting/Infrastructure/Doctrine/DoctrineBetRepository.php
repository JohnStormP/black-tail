<?php

namespace App\Betting\Infrastructure\Doctrine;

use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Exception\BetNotFoundException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(BetRepositoryInterface::class, public: true)]
final class DoctrineBetRepository implements BetRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Bet $bet): void
    {
        $this->entityManager->persist($bet);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(BetId $id): ?Bet
    {
        return $this->entityManager->find(Bet::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(BetId $id): Bet
    {
        $bet = $this->findById($id);

        if (null === $bet) {
            throw BetNotFoundException::withId($id);
        }

        return $bet;
    }

    public function getByIdForUpdate(BetId $id): Bet
    {
        $bet = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(Bet::class, 'b')
            ->where('b.id = :id')
            ->setParameter('id', $id->toString())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (null === $bet) {
            throw BetNotFoundException::withId($id);
        }

        return $bet;
    }

    public function findByIdempotencyKey(BetIdempotencyKey $key): ?Bet
    {
        return $this->entityManager->getRepository(Bet::class)->findOneBy(['idempotencyKey' => $key->toString()]);
    }
}
