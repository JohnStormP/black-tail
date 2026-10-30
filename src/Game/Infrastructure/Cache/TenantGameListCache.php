<?php

namespace App\Game\Infrastructure\Cache;

use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Infrastructure\Http\TenantGameJson;
use App\Tenant\Domain\ValueObject\TenantId;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class TenantGameListCache
{
    public function __construct(
        private readonly TenantGameRepositoryInterface $tenantGames,
        #[Autowire(service: 'cache.game_catalog')]
        private readonly CacheInterface $cache,
    ) {
    }

    public static function listKey(string $tenantId): string
    {
        return 'tenant_games.list.'.$tenantId;
    }

    /**
     * @return list<array{
     *     id: string,
     *     tenantId: string,
     *     gameId: string,
     *     enabled: bool,
     *     createdAt: string,
     * }>
     */
    public function listByTenant(TenantId $tenantId): array
    {
        $key = self::listKey($tenantId->toString());

        try {
            /** @var list<array{id: string, tenantId: string, gameId: string, enabled: bool, createdAt: string}> $cached */
            $cached = $this->cache->get($key, function (ItemInterface $item) use ($tenantId): array {
                $item->expiresAfter(3600);

                return array_map(TenantGameJson::from(...), $this->tenantGames->findByTenantId($tenantId));
            });
        } catch (InvalidArgumentException $e) {
            throw new \LogicException('Invalid tenant-game list cache key "'.$key.'".', 0, $e);
        }

        return $cached;
    }
}
