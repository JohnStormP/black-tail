<?php

namespace App\Wallet\Infrastructure\Doctrine;

use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(WalletRepositoryInterface::class, public: true)]
final class DoctrineWalletRepository implements WalletRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Wallet $wallet): void
    {
        $this->entityManager->persist($wallet);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(WalletId $id): ?Wallet
    {
        return $this->entityManager->find(Wallet::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(WalletId $id): Wallet
    {
        $wallet = $this->findById($id);
        if (null === $wallet) {
            throw WalletNotFoundException::withId($id);
        }

        return $wallet;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getByIdForUpdate(WalletId $id): Wallet
    {
        $wallet = $this->entityManager->find(
            Wallet::class,
            $id->toString(),
            LockMode::PESSIMISTIC_WRITE,
        );
        if (null === $wallet) {
            throw WalletNotFoundException::withId($id);
        }

        return $wallet;
    }

    /**
     * @return list<Wallet>
     */
    public function findByPlayerId(PlayerId $playerId): array
    {
        /** @var list<Wallet> $wallets */
        $wallets = $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(Wallet::class, 'w')
            ->where('w.playerId = :playerId')
            ->setParameter('playerId', $playerId->toString())
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $wallets;
    }
}
