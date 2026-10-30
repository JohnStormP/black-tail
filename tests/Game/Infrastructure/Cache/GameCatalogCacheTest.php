<?php

namespace App\Tests\Game\Infrastructure\Cache;

use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Application\Handler\RegisterGameHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Infrastructure\Cache\GameCatalogCache;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class GameCatalogCacheTest extends KernelTestCase
{
    private const string GAME_A = '01JC9K2M5N7P8Q3R4S6T7V8WG1';
    private const string GAME_B = '01JC9K2M5N7P8Q3R4S6T7V8WG2';

    public function testListServesCachedPayloadUntilRegisterInvalidates(): void
    {
        self::bootKernel();

        $games = static::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        $catalog = static::getContainer()->get(GameCatalogCache::class);
        self::assertInstanceOf(GameCatalogCache::class, $catalog);
        $cache = static::getContainer()->get('cache.game_catalog');
        self::assertInstanceOf(CacheInterface::class, $cache);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.Game::class)->execute();
        $cache->delete(GameCatalogCache::LIST_KEY);
        $cache->delete(GameCatalogCache::itemKey(self::GAME_A));
        $cache->delete(GameCatalogCache::itemKey(self::GAME_B));

        new RegisterGameHandler($games)(new RegisterGameCommand(self::GAME_A, 'lucky_reels', 'Lucky Reels'));

        $first = $catalog->list();
        self::assertCount(1, $first);
        self::assertSame(self::GAME_A, $first[0]['id']);

        $em->createQuery('DELETE FROM '.Game::class)->execute();
        $em->clear();

        $cached = $catalog->list();
        self::assertCount(1, $cached);
        self::assertSame(self::GAME_A, $cached[0]['id']);

        new RegisterGameHandler($games)(new RegisterGameCommand(self::GAME_B, 'crash', 'Crash'));

        $afterRegister = $catalog->list();
        self::assertCount(1, $afterRegister);
        self::assertSame(self::GAME_B, $afterRegister[0]['id']);

        $byId = $catalog->get(GameId::fromString(self::GAME_B));
        self::assertSame('Crash', $byId['name']);
    }
}
