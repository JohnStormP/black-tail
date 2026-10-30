<?php

namespace App\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\TenantGameNotFoundException;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Game\Infrastructure\Cache\TenantGameListCache;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

#[AsAlias(TenantGameRepositoryInterface::class, public: true)]
final class DoctrineTenantGameRepository implements TenantGameRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'cache.game_catalog')]
        private readonly CacheInterface $gameCatalogCache,
    ) {
    }

    public function save(TenantGame $tenantGame): void
    {
        $this->entityManager->persist($tenantGame);
        $this->entityManager->flush();
        $this->deleteTenantListKey($tenantGame->tenantId()->toString());
    }

    private function deleteTenantListKey(string $tenantId): void
    {
        $key = TenantGameListCache::listKey($tenantId);
        try {
            $this->gameCatalogCache->delete($key);
        } catch (InvalidArgumentException $e) {
            throw new \LogicException('Invalid tenant-game list cache key "'.$key.'".', 0, $e);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(TenantGameId $id): ?TenantGame
    {
        return $this->entityManager->find(TenantGame::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(TenantGameId $id): TenantGame
    {
        $tenantGame = $this->findById($id);
        if (null === $tenantGame) {
            throw TenantGameNotFoundException::withId($id);
        }

        return $tenantGame;
    }

    public function findByTenantAndGame(TenantId $tenantId, GameId $gameId): ?TenantGame
    {
        return $this->entityManager->getRepository(TenantGame::class)->findOneBy([
            'tenantId' => $tenantId->toString(),
            'gameId' => $gameId->toString(),
        ]);
    }

    /**
     * @return list<TenantGame>
     */
    public function findByTenantId(TenantId $tenantId): array
    {
        /** @var list<TenantGame> $links */
        $links = $this->entityManager->createQueryBuilder()
            ->select('tg')
            ->from(TenantGame::class, 'tg')
            ->where('tg.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('tg.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $links;
    }
}
