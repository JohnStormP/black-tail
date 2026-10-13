<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\SpinLuckyReelsCommand;
use App\Game\Application\Handler\SpinLuckyReelsHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Game\Domain\Exception\GameProviderNotFoundException;
use App\Game\Domain\Provider\GameProviderResolver;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\GameRoundRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Game\Infrastructure\Provider\LuckyReelsGameProvider;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpinLuckyReelsHandlerTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string GAME_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string TENANT_GAME_ID = '01EXYZ00000000000000000003';

    private SpinLuckyReelsHandler $handler;
    private GameRepositoryInterface $games;
    private GameRoundRepositoryInterface $rounds;
    private TenantGameRepositoryInterface $tenantGames;
    private PlayerRepositoryInterface $players;

    protected function setUp(): void
    {
        self::bootKernel();

        $games = self::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        $this->games = $games;

        $rounds = self::getContainer()->get(GameRoundRepositoryInterface::class);
        self::assertInstanceOf(GameRoundRepositoryInterface::class, $rounds);
        $this->rounds = $rounds;

        $tenantGames = self::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);
        $this->tenantGames = $tenantGames;

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $this->players = $players;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.GameRound::class)->execute();
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();

        $resolver = new GameProviderResolver([
            new LuckyReelsGameProvider(new LuckyReelsEngine()),
        ]);

        $this->handler = new SpinLuckyReelsHandler(
            $this->games,
            $this->rounds,
            $this->tenantGames,
            $this->players,
            $resolver,
            new SeededRandomIntGenerator(42),
        );
    }

    public function testSpinPersistsRound(): void
    {
        $this->seedPlayableGame();

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));

        $round = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID));
        $this->assertSame(self::GAME_ID, $round->gameId()->toString());
        $this->assertSame(self::PLAYER_ID, $round->playerId()->toString());
        $this->assertSame(100, $round->betMinor());
        $this->assertCount(5, $round->stops());
        $this->assertSame($round->winMinor() > 0, $round->isWin());
    }

    public function testSpinIsDeterministicWithSeededRng(): void
    {
        $this->seedPlayableGame();

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));

        $first = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID));
        $firstStops = $first->stops();
        $firstMultiplier = $first->totalMultiplier();
        $firstWinMinor = $first->winMinor();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.GameRound::class)->execute();
        $entityManager->clear();

        $handler = new SpinLuckyReelsHandler(
            $this->games,
            $this->rounds,
            $this->tenantGames,
            $this->players,
            new GameProviderResolver([new LuckyReelsGameProvider(new LuckyReelsEngine())]),
            new SeededRandomIntGenerator(42),
        );

        ($handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));

        $second = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID));
        $this->assertSame($firstStops, $second->stops());
        $this->assertSame($firstMultiplier, $second->totalMultiplier());
        $this->assertSame($firstWinMinor, $second->winMinor());
    }

    public function testRejectsDisabledCatalogGame(): void
    {
        $game = Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        );
        $game->disable();
        $this->games->save($game);
        $this->seedPlayer();

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));
    }

    public function testRejectsWhenGameNotAssignedToTenant(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
        $this->seedPlayer();

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));
    }

    public function testRejectsWhenTenantGameDisabled(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
        $this->seedPlayer();

        $link = TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        );
        $link->disable();
        $this->tenantGames->save($link);

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));
    }

    public function testRejectsUnknownPlayer(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));

        $this->expectException(PlayerNotFoundException::class);

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));
    }

    public function testRejectsUnsupportedGameCode(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('crash'),
            'Crash',
        ));
        $this->seedPlayer();
        $this->tenantGames->save(TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        ));

        $this->expectException(GameProviderNotFoundException::class);

        ($this->handler)(new SpinLuckyReelsCommand(
            self::ROUND_ID,
            self::GAME_ID,
            self::PLAYER_ID,
            100,
        ));
    }

    private function seedPlayableGame(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
        $this->seedPlayer();
        $this->tenantGames->save(TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        ));
    }

    private function seedPlayer(): void
    {
        $this->players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));
    }
}
