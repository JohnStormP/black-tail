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
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpinLuckyReelsHandlerTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string GAME_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string TENANT_GAME_ID = '01EXYZ00000000000000000003';
    private const string WALLET_ID = '01FXYZ00000000000000000004';
    private const string DEPOSIT_ENTRY_ID = '01GXYZ00000000000000000005';
    private const string RESERVE_ENTRY_ID = '01HXYZ00000000000000000006';
    private const string SETTLE_ENTRY_ID = '01JXYZ00000000000000000007';
    private const string PAYOUT_ENTRY_ID = '01KXYZ00000000000000000008';
    private const string ROUND_ID_2 = '01MXYZ00000000000000000009';
    private const string RESERVE_ENTRY_ID_2 = '01NXYZ0000000000000000000A';
    private const string SETTLE_ENTRY_ID_2 = '01PXYZ0000000000000000000B';
    private const string PAYOUT_ENTRY_ID_2 = '01QXYZ0000000000000000000C';

    private SpinLuckyReelsHandler $handler;
    private GameRepositoryInterface $games;
    private GameRoundRepositoryInterface $rounds;
    private TenantGameRepositoryInterface $tenantGames;
    private PlayerRepositoryInterface $players;
    private WalletRepositoryInterface $wallets;
    private LedgerEntryRepositoryInterface $ledger;
    private TransactionManagerInterface $transactions;

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

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $this->ledger = $ledger;

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);
        $this->transactions = $transactions;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.GameRound::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
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
            $this->wallets,
            $this->ledger,
            $this->transactions,
            $resolver,
            new SeededRandomIntGenerator(42),
        );
    }

    public function testSpinPersistsRoundAndMovesWallet(): void
    {
        $this->seedPlayableGame();
        $this->seedFundedWallet(5000);

        ($this->handler)($this->command());

        $round = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID));
        $this->assertSame(self::GAME_ID, $round->gameId()->toString());
        $this->assertSame(self::PLAYER_ID, $round->playerId()->toString());
        $this->assertSame(100, $round->betMinor());
        $this->assertCount(5, $round->stops());

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(5000 - 100 + $round->winMinor(), $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());
    }

    public function testSpinIsIdempotentForSameRoundId(): void
    {
        $this->seedPlayableGame();
        $this->seedFundedWallet(5000);

        ($this->handler)($this->command());
        ($this->handler)($this->command());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $count = (int) $entityManager->createQuery(
            'SELECT COUNT(r.id) FROM '.GameRound::class.' r'
        )->getSingleScalarResult();
        $this->assertSame(1, $count);

        $round = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID));
        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(5000 - 100 + $round->winMinor(), $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());
    }

    public function testSpinIsDeterministicWithSeededRng(): void
    {
        $this->seedPlayableGame();
        $this->seedFundedWallet(5000);

        ($this->handler)($this->command());

        $first = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID));
        $firstStops = $first->stops();
        $firstMultiplier = $first->totalMultiplier();
        $firstWinMinor = $first->winMinor();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class.' e WHERE e.id != :deposit')
            ->setParameter('deposit', self::DEPOSIT_ENTRY_ID)
            ->execute();
        $entityManager->createQuery('DELETE FROM '.GameRound::class)->execute();
        $entityManager->clear();

        $handler = new SpinLuckyReelsHandler(
            $this->games,
            $this->rounds,
            $this->tenantGames,
            $this->players,
            $this->wallets,
            $this->ledger,
            $this->transactions,
            new GameProviderResolver([new LuckyReelsGameProvider(new LuckyReelsEngine())]),
            new SeededRandomIntGenerator(42),
        );

        ($handler)($this->command(
            roundId: self::ROUND_ID_2,
            reserveEntryId: self::RESERVE_ENTRY_ID_2,
            settleEntryId: self::SETTLE_ENTRY_ID_2,
            payoutEntryId: self::PAYOUT_ENTRY_ID_2,
        ));

        $second = $this->rounds->getById(GameRoundId::fromString(self::ROUND_ID_2));
        $this->assertSame($firstStops, $second->stops());
        $this->assertSame($firstMultiplier, $second->totalMultiplier());
        $this->assertSame($firstWinMinor, $second->winMinor());
    }

    public function testRejectsInsufficientFunds(): void
    {
        $this->seedPlayableGame();
        $open = new OpenWalletHandler($this->wallets);
        ($open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        try {
            ($this->handler)($this->command());
            $this->fail('Expected InsufficientFundsException');
        } catch (InsufficientFundsException) {
            $this->assertNull($this->rounds->findById(GameRoundId::fromString(self::ROUND_ID)));
        }
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
        $this->seedFundedWallet(5000);

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)($this->command());
    }

    public function testRejectsWhenGameNotAssignedToTenant(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
        $this->seedPlayer();
        $this->seedFundedWallet(5000);

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)($this->command());
    }

    public function testRejectsWhenTenantGameDisabled(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
        $this->seedPlayer();
        $this->seedFundedWallet(5000);

        $link = TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        );
        $link->disable();
        $this->tenantGames->save($link);

        $this->expectException(GameNotPlayableException::class);

        ($this->handler)($this->command());
    }

    public function testRejectsUnknownPlayer(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));

        $this->expectException(PlayerNotFoundException::class);

        ($this->handler)($this->command());
    }

    public function testRejectsUnsupportedGameCode(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('crash'),
            'Crash',
        ));
        $this->seedPlayer();
        $this->seedFundedWallet(5000);
        $this->tenantGames->save(TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        ));

        $this->expectException(GameProviderNotFoundException::class);

        ($this->handler)($this->command());
    }

    private function command(
        string $roundId = self::ROUND_ID,
        string $reserveEntryId = self::RESERVE_ENTRY_ID,
        string $settleEntryId = self::SETTLE_ENTRY_ID,
        string $payoutEntryId = self::PAYOUT_ENTRY_ID,
        int $betMinor = 100,
    ): SpinLuckyReelsCommand {
        return new SpinLuckyReelsCommand(
            $roundId,
            self::GAME_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            $betMinor,
            'USD',
            $reserveEntryId,
            $settleEntryId,
            $payoutEntryId,
        );
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

    private function seedFundedWallet(int $amountMinor): void
    {
        $open = new OpenWalletHandler($this->wallets);
        ($open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        $deposit = new DepositWalletHandler($this->wallets, $this->ledger, $this->transactions);
        ($deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, $amountMinor));
    }
}
