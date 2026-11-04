<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\CashOutCrashBetCommand;
use App\Game\Application\Command\OpenCrashRoundCommand;
use App\Game\Application\Command\PlaceCrashBetCommand;
use App\Game\Application\Command\ResolveCrashRoundCommand;
use App\Game\Application\Command\StartCrashRoundCommand;
use App\Game\Application\Handler\CashOutCrashBetHandler;
use App\Game\Application\Handler\OpenCrashRoundHandler;
use App\Game\Application\Handler\PlaceCrashBetHandler;
use App\Game\Application\Handler\ResolveCrashRoundHandler;
use App\Game\Application\Handler\StartCrashRoundHandler;
use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\Service\CrashEngine;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Domain\ValueObject\CrashRoundStatus;
use App\Player\Domain\Entity\Player;
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
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class CrashRoundFlowHandlerTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string BET_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string WALLET_ID = '01EXYZ00000000000000000003';
    private const string DEPOSIT_ENTRY_ID = '01FXYZ00000000000000000004';
    private const string RESERVE_ENTRY_ID = '01GXYZ00000000000000000005';
    private const string SETTLE_ENTRY_ID = '01HXYZ00000000000000000006';
    private const string PAYOUT_ENTRY_ID = '01JXYZ00000000000000000007';

    private OpenCrashRoundHandler $openRound;
    private StartCrashRoundHandler $startRound;
    private PlaceCrashBetHandler $placeBet;
    private CashOutCrashBetHandler $cashOut;
    private ResolveCrashRoundHandler $resolve;
    private CrashRoundRepositoryInterface $rounds;
    private CrashBetRepositoryInterface $bets;
    private WalletRepositoryInterface $wallets;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.CrashBet::class)->execute();
        $em->createQuery('DELETE FROM '.CrashRound::class)->execute();
        $em->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $em->createQuery('DELETE FROM '.Wallet::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();

        $rounds = self::getContainer()->get(CrashRoundRepositoryInterface::class);
        self::assertInstanceOf(CrashRoundRepositoryInterface::class, $rounds);
        $this->rounds = $rounds;

        $bets = self::getContainer()->get(CrashBetRepositoryInterface::class);
        self::assertInstanceOf(CrashBetRepositoryInterface::class, $bets);
        $this->bets = $bets;

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('crash-player@example.com'),
        ));

        $openWallet = new OpenWalletHandler($wallets);
        $deposit = new DepositWalletHandler($wallets, $ledger, $transactions);
        ($openWallet)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 10_000));

        $noopRealtime = new class implements CrashRealtimePublisherInterface {
            public function publishStarted(CrashRoundId $roundId): void
            {
            }

            public function publishTick(CrashRoundId $roundId, int $hundredths): void
            {
            }

            public function publishCrashed(CrashRoundId $roundId, int $crashPointHundredths): void
            {
            }
        };

        $this->clock = new MockClock('2026-10-21 12:00:00.000000');
        $this->openRound = new OpenCrashRoundHandler($rounds, new CrashEngine(), new SeededRandomIntGenerator(42));
        $this->startRound = new StartCrashRoundHandler($rounds, $transactions, $noopRealtime, $this->clock);
        $this->placeBet = new PlaceCrashBetHandler($rounds, $bets, $wallets, $ledger, $transactions);
        $this->cashOut = new CashOutCrashBetHandler($rounds, $bets, $wallets, $ledger, $transactions, $this->clock);
        $this->resolve = new ResolveCrashRoundHandler($rounds, $bets, $wallets, $ledger, $transactions, $noopRealtime);
    }

    public function testOpenAndStartRound(): void
    {
        ($this->openRound)(new OpenCrashRoundCommand(self::ROUND_ID));
        $round = $this->rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $this->assertSame(CrashRoundStatus::Waiting, $round->status());

        ($this->startRound)(new StartCrashRoundCommand(self::ROUND_ID));
        $round = $this->rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $this->assertSame(CrashRoundStatus::Running, $round->status());
    }

    public function testPlaceBetReservesWalletWhileWaiting(): void
    {
        ($this->openRound)(new OpenCrashRoundCommand(self::ROUND_ID));
        ($this->placeBet)(new PlaceCrashBetCommand(
            self::BET_ID,
            self::ROUND_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            self::RESERVE_ENTRY_ID,
            500,
            'USD',
        ));
        $bet = $this->bets->getById(CrashBetId::fromString(self::BET_ID));
        $this->assertSame(CrashBetStatus::Open, $bet->status());
        $this->assertSame(500, $bet->amount()->amount());
        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(500, $wallet->reservedBalance()->amount());
        $this->assertSame(9500, $wallet->availableBalance()->amount());
    }

    public function testCashOutCreditsPayout(): void
    {
        ($this->openRound)(new OpenCrashRoundCommand(self::ROUND_ID));
        ($this->placeBet)(new PlaceCrashBetCommand(
            self::BET_ID,
            self::ROUND_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            self::RESERVE_ENTRY_ID,
            500,
            'USD',
        ));
        ($this->startRound)(new StartCrashRoundCommand(self::ROUND_ID));

        ($this->cashOut)(new CashOutCrashBetCommand(
            self::BET_ID,
            self::SETTLE_ENTRY_ID,
            self::PAYOUT_ENTRY_ID,
        ));
        $bet = $this->bets->getById(CrashBetId::fromString(self::BET_ID));
        $this->assertSame(CrashBetStatus::CashedOut, $bet->status());
        $this->assertSame(100, $bet->cashOutMultiplier()?->toHundredths());
        $this->assertSame(500, $bet->payoutMinor());
        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(0, $wallet->reservedBalance()->amount());
        $this->assertSame(10_000, $wallet->availableBalance()->amount());
    }

    public function testResolveCrashMarksOpenBetLost(): void
    {
        ($this->openRound)(new OpenCrashRoundCommand(self::ROUND_ID));
        ($this->placeBet)(new PlaceCrashBetCommand(
            self::BET_ID,
            self::ROUND_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            self::RESERVE_ENTRY_ID,
            500,
            'USD',
        ));
        ($this->startRound)(new StartCrashRoundCommand(self::ROUND_ID));
        ($this->resolve)(new ResolveCrashRoundCommand(
            self::ROUND_ID,
            [self::BET_ID => self::SETTLE_ENTRY_ID],
        ));
        $round = $this->rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $this->assertSame(CrashRoundStatus::Crashed, $round->status());
        $bet = $this->bets->getById(CrashBetId::fromString(self::BET_ID));
        $this->assertSame(CrashBetStatus::Lost, $bet->status());
        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(0, $wallet->reservedBalance()->amount());
        $this->assertSame(9500, $wallet->availableBalance()->amount());
    }
}
