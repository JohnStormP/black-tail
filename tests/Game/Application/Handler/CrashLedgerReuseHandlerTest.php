<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\CashOutCrashBetCommand;
use App\Game\Application\Command\OpenCrashRoundCommand;
use App\Game\Application\Command\PlaceCrashBetCommand;
use App\Game\Application\Command\StartCrashRoundCommand;
use App\Game\Application\Handler\CashOutCrashBetHandler;
use App\Game\Application\Handler\OpenCrashRoundHandler;
use App\Game\Application\Handler\PlaceCrashBetHandler;
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
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CrashLedgerReuseHandlerTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FZZ';
    private const string BET_A = '01BX5ZZKBKACTAV9WEVGEMMVR1';
    private const string BET_B = '01BX5ZZKBKACTAV9WEVGEMMVR2';
    private const string PLAYER_ID = '01CXYZ00000000000000000011';
    private const string TENANT_ID = '01DXYZ00000000000000000012';
    private const string WALLET_ID = '01EXYZ00000000000000000013';
    private const string DEPOSIT_ENTRY_ID = '01FXYZ00000000000000000014';
    private const string RESERVE_ENTRY_ID = '01GXYZ00000000000000000015';
    private const string RESERVE_B = '01GXYZ00000000000000000025';
    private const string SETTLE_ENTRY_ID = '01HXYZ00000000000000000016';
    private const string PAYOUT_ENTRY_ID = '01JXYZ00000000000000000017';

    private OpenCrashRoundHandler $openRound;
    private StartCrashRoundHandler $startRound;
    private PlaceCrashBetHandler $placeBet;
    private CashOutCrashBetHandler $cashOut;
    private CrashBetRepositoryInterface $bets;
    private CrashRoundRepositoryInterface $rounds;
    private WalletRepositoryInterface $wallets;

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
            PlayerEmail::fromString('crash-ledger-reuse@example.com'),
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

        $this->openRound = new OpenCrashRoundHandler($rounds, new CrashEngine(), new SeededRandomIntGenerator(42));
        $this->startRound = new StartCrashRoundHandler($rounds, $transactions, $noopRealtime);
        $this->placeBet = new PlaceCrashBetHandler($rounds, $bets, $wallets, $ledger, $transactions);
        $this->cashOut = new CashOutCrashBetHandler($rounds, $bets, $wallets, $ledger, $transactions);
    }

    public function testSecondPlaceWithSameReserveEntryIdConflicts(): void
    {
        ($this->openRound)(new OpenCrashRoundCommand(self::ROUND_ID));
        ($this->placeBet)(new PlaceCrashBetCommand(
            self::BET_A,
            self::ROUND_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            self::RESERVE_ENTRY_ID,
            500,
            'USD',
        ));

        try {
            ($this->placeBet)(new PlaceCrashBetCommand(
                self::BET_B,
                self::ROUND_ID,
                self::PLAYER_ID,
                self::WALLET_ID,
                self::RESERVE_ENTRY_ID,
                500,
                'USD',
            ));
            $this->fail('Expected LedgerIdempotencyConflictException');
        } catch (LedgerIdempotencyConflictException) {
        }

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(500, $wallet->reservedBalance()->amount());
        $this->assertNull($this->bets->findById(CrashBetId::fromString(self::BET_B)));
    }

    public function testSecondCashOutWithSameSettleEntryIdConflicts(): void
    {
        ($this->openRound)(new OpenCrashRoundCommand(self::ROUND_ID));
        ($this->placeBet)(new PlaceCrashBetCommand(
            self::BET_A,
            self::ROUND_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            self::RESERVE_ENTRY_ID,
            500,
            'USD',
        ));
        ($this->placeBet)(new PlaceCrashBetCommand(
            self::BET_B,
            self::ROUND_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            self::RESERVE_B,
            500,
            'USD',
        ));
        ($this->startRound)(new StartCrashRoundCommand(self::ROUND_ID));

        $round = $this->rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $cashOutAt = min(150, $round->crashPoint()->toHundredths() - 1);

        ($this->cashOut)(new CashOutCrashBetCommand(
            self::BET_A,
            self::SETTLE_ENTRY_ID,
            self::PAYOUT_ENTRY_ID,
            $cashOutAt,
        ));

        try {
            ($this->cashOut)(new CashOutCrashBetCommand(
                self::BET_B,
                self::SETTLE_ENTRY_ID,
                self::PAYOUT_ENTRY_ID,
                $cashOutAt,
            ));
            $this->fail('Expected LedgerIdempotencyConflictException');
        } catch (LedgerIdempotencyConflictException) {
        }

        $betB = $this->bets->getById(CrashBetId::fromString(self::BET_B));
        $this->assertSame(CrashBetStatus::Open, $betB->status());
    }
}
