<?php

namespace App\Tests\Betting\Application\Handler;

use App\Betting\Application\Command\PlaceBetCommand;
use App\Betting\Application\Handler\PlaceBetHandler;
use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Event\BetPlaced;
use App\Betting\Domain\Exception\BetIdempotencyConflictException;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class PlaceBetHandlerTest extends KernelTestCase
{
    private const string BET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';
    private const string ROUND_ID = '01DXYZ00000000000000000002';
    private const string TENANT_ID = '01EXYZ00000000000000000003';
    private const string TENANT_GAME_ID = '01FXYZ00000000000000000004';
    private const string IDEMPOTENCY_KEY = 'place-bet-001';

    private PlaceBetHandler $handler;
    private BetRepositoryInterface $bets;
    private EntityManagerInterface $entityManager;

    /** @var list<BetPlaced> */
    private array $recordedEvents = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $bets = self::getContainer()->get(BetRepositoryInterface::class);
        self::assertInstanceOf(BetRepositoryInterface::class, $bets);
        $this->bets = $bets;

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);

        $games = self::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);

        $tenantGames = self::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $entityManager->createQuery('DELETE FROM '.Bet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();

        $games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));
        $tenantGames->save(TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        ));

        $this->recordedEvents = [];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BetPlaced::class, function (BetPlaced $event): void {
            $this->recordedEvents[] = $event;
        });

        $this->handler = new PlaceBetHandler($this->bets, $players, $games, $tenantGames, $dispatcher);
    }

    public function testPlacesPendingBet(): void
    {
        ($this->handler)($this->command());

        $bet = $this->bets->getById(BetId::fromString(self::BET_ID));
        $this->assertSame(BetStatus::Pending, $bet->status());
        $this->assertSame(100, $bet->amount()->amount());
        $this->assertSame(self::IDEMPOTENCY_KEY, $bet->idempotencyKey()->toString());
        $this->assertCount(1, $this->recordedEvents);
        $this->assertTrue($this->recordedEvents[0]->betId()->equals(BetId::fromString(self::BET_ID)));
    }

    public function testPlaceIsIdempotentForSamePayload(): void
    {
        ($this->handler)($this->command());
        ($this->handler)($this->command());

        $count = (int) $this->entityManager->createQuery(
            'SELECT COUNT(b.id) FROM '.Bet::class.' b'
        )->getSingleScalarResult();
        $this->assertSame(1, $count);
        $this->assertCount(1, $this->recordedEvents);
    }

    public function testPlaceConflictsOnSameKeyDifferentPayload(): void
    {
        ($this->handler)($this->command());

        $this->expectException(BetIdempotencyConflictException::class);

        ($this->handler)($this->command(
            betId: '01GXYZ00000000000000000005',
            amountMinor: 200,
        ));
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(InvalidBetException::class);

        ($this->handler)($this->command(amountMinor: 0));
    }

    public function testRejectsUnknownPlayer(): void
    {
        $this->expectException(PlayerNotFoundException::class);

        ($this->handler)($this->command(playerId: '01HXYZ00000000000000000006'));
    }

    public function testRejectsWhenTenantGameDisabled(): void
    {
        $link = $this->entityManager->find(TenantGame::class, self::TENANT_GAME_ID);
        self::assertInstanceOf(TenantGame::class, $link);
        $link->disable();
        $this->entityManager->flush();

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)($this->command());
    }

    public function testRejectsWhenGameNotAssigned(): void
    {
        $this->entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)($this->command());
    }

    private function command(
        ?string $betId = null,
        ?string $playerId = null,
        ?int $amountMinor = null,
    ): PlaceBetCommand {
        return new PlaceBetCommand(
            $betId ?? self::BET_ID,
            $playerId ?? self::PLAYER_ID,
            self::GAME_ID,
            self::ROUND_ID,
            $amountMinor ?? 100,
            'USD',
            self::IDEMPOTENCY_KEY,
        );
    }
}
