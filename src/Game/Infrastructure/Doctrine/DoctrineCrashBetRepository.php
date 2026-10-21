<?php

namespace App\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Exception\CrashBetNotFoundException;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Game\Domain\ValueObject\CrashRoundId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CrashBetRepositoryInterface::class, public: true)]
final class DoctrineCrashBetRepository implements CrashBetRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CrashBet $bet): void
    {
        $this->entityManager->persist($bet);
        $this->entityManager->flush();
    }

    public function findById(CrashBetId $id): ?CrashBet
    {
        return $this->entityManager->find(CrashBet::class, $id->toString());
    }

    public function getById(CrashBetId $id): CrashBet
    {
        $bet = $this->findById($id);
        if (null === $bet) {
            throw CrashBetNotFoundException::withId($id);
        }

        return $bet;
    }

    public function findOpenByRoundId(CrashRoundId $roundId): array
    {
        /** @var list<CrashBet> $bets */
        $bets = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(CrashBet::class, 'b')
            ->where('b.roundId = :roundId')
            ->andWhere('b.status = :status')
            ->setParameter('roundId', $roundId->toString())
            ->setParameter('status', CrashBetStatus::Open)
            ->getQuery()
            ->getResult();

        return $bets;
    }
}
