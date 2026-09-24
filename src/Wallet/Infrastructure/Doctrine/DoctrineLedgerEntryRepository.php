<?php

namespace App\Wallet\Infrastructure\Doctrine;

use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerEntryNotFoundException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(LedgerEntryRepositoryInterface::class, public: true)]
final class DoctrineLedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(LedgerEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(LedgerEntryId $id): ?LedgerEntry
    {
        return $this->entityManager->find(LedgerEntry::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(LedgerEntryId $id): LedgerEntry
    {
        $entry = $this->findById($id);
        if (null === $entry) {
            throw LedgerEntryNotFoundException::withId($id);
        }

        return $entry;
    }

    /**
     * @return list<LedgerEntry>
     */
    public function findByWalletId(WalletId $walletId): array
    {
        /** @var list<LedgerEntry> $entries */
        $entries = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(LedgerEntry::class, 'e')
            ->where('e.walletId = :walletId')
            ->setParameter('walletId', $walletId->toString())
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $entries;
    }
}
