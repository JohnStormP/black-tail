<?php

namespace App\Game\Infrastructure\Cache;

use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Infrastructure\Http\GameJson;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class GameCatalogCache
{
    public const string LIST_KEY = 'game.catalog.list';

    public function __construct(
        private readonly GameRepositoryInterface $games,
        #[Autowire(service: 'cache.game_catalog')]
        private readonly CacheInterface $cache,
    ) {
    }

    public static function itemKey(string $gameId): string
    {
        return 'game.catalog.item.'.$gameId;
    }

    /**
     * @return list<array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     status: string,
     *     createdAt: string,
     * }>
     */
    public function list(): array
    {
        try {
            /** @var list<array{id: string, code: string, name: string, status: string, createdAt: string}> $cached */
            $cached = $this->cache->get(self::LIST_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(3600);

                return array_map(GameJson::from(...), $this->games->findAll());
            });
        } catch (InvalidArgumentException $e) {
            throw new \LogicException('Invalid game catalog cache key "'.self::LIST_KEY.'".', 0, $e);
        }

        return $cached;
    }

    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     status: string,
     *     createdAt: string,
     * }
     */
    public function get(GameId $id): array
    {
        $key = self::itemKey($id->toString());

        try {
            /** @var array{id: string, code: string, name: string, status: string, createdAt: string} $cached */
            $cached = $this->cache->get($key, function (ItemInterface $item) use ($id): array {
                $item->expiresAfter(3600);

                return GameJson::from($this->games->getById($id));
            });
        } catch (InvalidArgumentException $e) {
            throw new \LogicException('Invalid game catalog cache key "'.$key.'".', 0, $e);
        }

        return $cached;
    }
}
