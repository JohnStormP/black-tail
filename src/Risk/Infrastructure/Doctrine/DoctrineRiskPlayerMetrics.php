<?php

namespace App\Risk\Infrastructure\Doctrine;

use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Domain\Service\RiskPlayerMetricsInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(RiskPlayerMetricsInterface::class, public: true)]
final class DoctrineRiskPlayerMetrics implements RiskPlayerMetricsInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sumDepositMinorSince(PlayerId $playerId, \DateTimeImmutable $since): int
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(p.amountMinor), 0)')
            ->from(Payment::class, 'p')
            ->where('p.playerId = :playerId')
            ->andWhere('p.type = :type')
            ->andWhere('p.status IN (:statuses)')
            ->andWhere('p.createdAt >= :since')
            ->setParameter('playerId', $playerId->toString())
            ->setParameter('type', PaymentType::Deposit)
            ->setParameter('statuses', [
                PaymentStatus::Pending,
                PaymentStatus::Processing,
                PaymentStatus::Completed,
            ])
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function sumBettingMinorSince(PlayerId $playerId, \DateTimeImmutable $since): int
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(b.amountMinor), 0)')
            ->from(Bet::class, 'b')
            ->where('b.playerId = :playerId')
            ->andWhere('b.status NOT IN (:excluded)')
            ->andWhere('b.createdAt >= :since')
            ->setParameter('playerId', $playerId->toString())
            ->setParameter('excluded', [BetStatus::Cancelled, BetStatus::Refunded])
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function countBetsSince(PlayerId $playerId, \DateTimeImmutable $since): int
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Bet::class, 'b')
            ->where('b.playerId = :playerId')
            ->andWhere('b.status NOT IN (:excluded)')
            ->andWhere('b.createdAt >= :since')
            ->setParameter('playerId', $playerId->toString())
            ->setParameter('excluded', [BetStatus::Cancelled, BetStatus::Refunded])
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
