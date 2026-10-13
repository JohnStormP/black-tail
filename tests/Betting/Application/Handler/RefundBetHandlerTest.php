<?php

namespace App\Tests\Betting\Application\Handler;

use App\Betting\Application\Command\AcceptBetCommand;
use App\Betting\Application\Command\PlaceBetCommand;
use App\Betting\Application\Command\RefundBetCommand;
use App\Betting\Application\Handler\AcceptBetHandler;
use App\Betting\Application\Handler\PlaceBetHandler;
use App\Betting\Application\Handler\RefundBetHandler;
use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Exception\InvalidBetTransitionException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class RefundBetHandlerTest extends KernelTestCase
{
    private const string BET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string OTHER_PLAYER_ID = '01HXYZ00000000000000000006';
    private const string GAME_ID = '01CXYZ00000000000000000001';
    private const string ROUND_ID = '01DXYZ00000000000000000002';
    private const string TENANT_ID = '01EXYZ00000000000000000003';
    private const string TENANT_GAME_ID = '01FXYZ00000000000000000004';
    private const string WALLET_ID = '01GXYZ00000000000000000005';
    private const string OTHER_WALLET_ID = '01JXYZ00000000000000000007';
    private const string DEPOSIT_ENTRY_ID = '01KXYZ00000000000000000008';
    private const string RESERVE_ENTRY_ID = '01MXYZ00000000000000000009';
    private const string RELEASE_ENTRY_ID = '01NXYZ0000000000000000000A';
    private const string IDEMPOTENCY_KEY = 'place-bet-001';

    private PlaceBetHandler $place;
    private AcceptBetHandler $accept;
    private RefundBetHandler $refund;
    private OpenWalletHandler $open;
    private DepositWalletHandler $deposit;
    private BetRepositoryInterface $bets;
    private WalletRepositoryInterface $wallets;
    private LedgerEntryRepositoryInterface $ledger;

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

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $this->ledger = $ledger;

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Bet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
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

        $this->place = new PlaceBetHandler($this->bets, $players, $games, $tenantGames, new EventDispatcher());
        $this->accept = new AcceptBetHandler($this->bets, $this->wallets, $this->ledger, $transactions);
        $this->refund = new RefundBetHandler($this->bets, $this->wallets, $this->ledger, $transactions);
        $this->open = new OpenWalletHandler($this->wallets);
        $this->deposit = new DepositWalletHandler($this->wallets, $this->ledger, $transactions);
    }

    public function testRefundReleasesReservedStake(): void
    {
        $this->seedAcceptedBet(1000, 100);

        ($this->refund)(new RefundBetCommand(self::BET_ID, self::WALLET_ID, self::RELEASE_ENTRY_ID));

        $bet = $this->bets->getById(BetId::fromString(self::BET_ID));
        $this->assertSame(BetStatus::Refunded, $bet->status());

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());

        $entry = $this->ledger->getById(LedgerEntryId::fromString(self::RELEASE_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Release, $entry->type());
        $this->assertSame(100, $entry->amount()->amount());
    }

    public function testRefundIsIdempotent(): void
    {
        $this->seedAcceptedBet(1000, 100);

        $command = new RefundBetCommand(self::BET_ID, self::WALLET_ID, self::RELEASE_ENTRY_ID);
        ($this->refund)($command);
        ($this->refund)($command);

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());
        $this->assertSame(BetStatus::Refunded, $this->bets->getById(BetId::fromString(self::BET_ID))->status());
    }

    public function testRejectsRefundFromPending(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));
        ($this->place)(new PlaceBetCommand(
            self::BET_ID,
            self::PLAYER_ID,
            self::GAME_ID,
            self::ROUND_ID,
            100,
            'USD',
            self::IDEMPOTENCY_KEY,
        ));

        $this->expectException(InvalidBetTransitionException::class);

        ($this->refund)(new RefundBetCommand(self::BET_ID, self::WALLET_ID, self::RELEASE_ENTRY_ID));
    }

    public function testRejectsWalletOfAnotherPlayer(): void
    {
        $this->seedAcceptedBet(1000, 100);

        ($this->open)(new OpenWalletCommand(self::OTHER_WALLET_ID, self::OTHER_PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::OTHER_WALLET_ID, '01PXYZ0000000000000000000B', 1000));

        $this->expectException(InvalidBetException::class);

        ($this->refund)(new RefundBetCommand(self::BET_ID, self::OTHER_WALLET_ID, self::RELEASE_ENTRY_ID));
    }

    private function seedAcceptedBet(int $depositMinor, int $betMinor): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, $depositMinor));
        ($this->place)(new PlaceBetCommand(
            self::BET_ID,
            self::PLAYER_ID,
            self::GAME_ID,
            self::ROUND_ID,
            $betMinor,
            'USD',
            self::IDEMPOTENCY_KEY,
        ));
        ($this->accept)(new AcceptBetCommand(self::BET_ID, self::WALLET_ID, self::RESERVE_ENTRY_ID));
    }
}
