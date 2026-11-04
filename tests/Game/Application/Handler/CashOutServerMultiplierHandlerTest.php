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
use App\Game\Domain\Exception\InvalidCrashBetException;
use App\Game\Domain\Exception\InvalidCrashBetTransitionException;
use App\Game\Domain\Exception\InvalidCrashRoundTransitionException;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\Service\CrashEngine;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Game\Domain\ValueObject\CrashRoundId;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class CashOutServerMultiplierHandlerTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FYA';
    private const string BET_ID = '01BX5ZZKBKACTAV9WEVGEMMVYA';
    private const string PLAYER_ID = '01CXYZ00000000000000000021';
    private const string TENANT_ID = '01DXYZ00000000000000000022';
    private const string WALLET_ID = '01EXYZ00000000000000000023';
    private const string DEPOSIT_ENTRY_ID = '01FXYZ00000000000000000024';
    private const string RESERVE_ENTRY_ID = '01GXYZ00000000000000000025';
    private const string SETTLE_ENTRY_ID = '01HXYZ00000000000000000026';
    private const string PAYOUT_ENTRY_ID = '01JXYZ00000000000000000027';
    private const string SETTLE_AFTER_RESOLVE = '01HXYZ00000000000000000036';
    private const string PAYOUT_AFTER_RESOLVE = '01JXYZ00000000000000000037';

    private OpenCrashRoundHandler $openRound;
    private StartCrashRoundHandler $startRound;
    private PlaceCrashBetHandler $placeBet;
    private ResolveCrashRoundHandler $resolve;
    private CrashBetRepositoryInterface $bets;
    private MockClock $clock;
    private CashOutCrashBetHandler $cashOut;

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

        $bets = self::getContainer()->get(CrashBetRepositoryInterface::class);
        self::assertInstanceOf(CrashBetRepositoryInterface::class, $bets);
        $this->bets = $bets;

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('crash-server-mult@example.com'),
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

    public function testCashOutUsesServerClockMultiplierAtStart(): void
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
    }

    public function testCashOutAfterResolveIsRejected(): void
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

        $bet = $this->bets->getById(CrashBetId::fromString(self::BET_ID));
        $this->assertSame(CrashBetStatus::Lost, $bet->status());

        try {
            ($this->cashOut)(new CashOutCrashBetCommand(
                self::BET_ID,
                self::SETTLE_AFTER_RESOLVE,
                self::PAYOUT_AFTER_RESOLVE,
            ));
            $this->fail('Expected cash-out after resolve to fail');
        } catch (InvalidCrashBetException|InvalidCrashBetTransitionException|InvalidCrashRoundTransitionException) {
        }

        $this->assertSame(CrashBetStatus::Lost, $this->bets->getById(CrashBetId::fromString(self::BET_ID))->status());
    }
}
