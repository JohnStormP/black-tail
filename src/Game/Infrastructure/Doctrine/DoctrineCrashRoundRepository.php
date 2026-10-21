<?php

namespace App\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Exception\CrashRoundNotFoundException;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CrashRoundRepositoryInterface::class, public: true)]
final class DoctrineCrashRoundRepository implements CrashRoundRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CrashRound $round): void
    {
        $this->entityManager->persist($round);
        $this->entityManager->flush();
    }

    public function findById(CrashRoundId $id): ?CrashRound
    {
        return $this->entityManager->find(CrashRound::class, $id->toString());
    }

    public function getById(CrashRoundId $id): CrashRound
    {
        $round = $this->findById($id);
        if (null === $round) {
            throw CrashRoundNotFoundException::withId($id);
        }

        return $round;
    }

    public function getByIdForUpdate(CrashRoundId $id): CrashRound
    {
        $round = $this->entityManager->find(
            CrashRound::class,
            $id->toString(),
            LockMode::PESSIMISTIC_WRITE,
        );
        if (null === $round) {
            throw CrashRoundNotFoundException::withId($id);
        }

        return $round;
    }
}
