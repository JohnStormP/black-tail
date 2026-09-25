<?php

namespace App\Tests\Wallet\Application\Handler;

use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Command\ReleaseWalletCommand;
use App\Wallet\Application\Command\ReserveWalletCommand;
use App\Wallet\Application\Command\SettleWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Application\Handler\ReleaseWalletHandler;
use App\Wallet\Application\Handler\ReserveWalletHandler;
use App\Wallet\Application\Handler\SettleWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReserveReleaseSettleWalletHandlerTest extends KernelTestCase
{
    private const string WALLET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string DEPOSIT_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string RESERVE_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';
    private const string RELEASE_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F03';
    private const string SETTLE_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F04';

    private OpenWalletHandler $open;
    private DepositWalletHandler $deposit;
    private ReserveWalletHandler $reserve;
    private ReleaseWalletHandler $release;
    private SettleWalletHandler $settle;
    private WalletRepositoryInterface $wallets;
    private LedgerEntryRepositoryInterface $ledger;

    protected function setUp(): void
    {
        self::bootKernel();

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
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();

        $this->open = new OpenWalletHandler($this->wallets);
        $this->deposit = new DepositWalletHandler($this->wallets, $this->ledger, $transactions);
        $this->reserve = new ReserveWalletHandler($this->wallets, $this->ledger, $transactions);
        $this->release = new ReleaseWalletHandler($this->wallets, $this->ledger, $transactions);
        $this->settle = new SettleWalletHandler($this->wallets, $this->ledger, $transactions);
    }

    public function testReserveReleaseAndSettle(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));

        ($this->reserve)(new ReserveWalletCommand(self::WALLET_ID, self::RESERVE_ENTRY_ID, 400));
        $afterReserve = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(600, $afterReserve->availableBalance()->amount());
        $this->assertSame(400, $afterReserve->reservedBalance()->amount());

        $reserveEntry = $this->ledger->getById(LedgerEntryId::fromString(self::RESERVE_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Reserve, $reserveEntry->type());
        $this->assertSame(400, $reserveEntry->amount()->amount());
        $this->assertSame(600, $reserveEntry->balanceAfter()->amount());

        ($this->release)(new ReleaseWalletCommand(self::WALLET_ID, self::RELEASE_ENTRY_ID, 100));
        $afterRelease = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(700, $afterRelease->availableBalance()->amount());
        $this->assertSame(300, $afterRelease->reservedBalance()->amount());

        $releaseEntry = $this->ledger->getById(LedgerEntryId::fromString(self::RELEASE_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Release, $releaseEntry->type());
        $this->assertSame(100, $releaseEntry->amount()->amount());
        $this->assertSame(700, $releaseEntry->balanceAfter()->amount());

        ($this->settle)(new SettleWalletCommand(self::WALLET_ID, self::SETTLE_ENTRY_ID, 200));
        $afterSettle = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(700, $afterSettle->availableBalance()->amount());
        $this->assertSame(100, $afterSettle->reservedBalance()->amount());

        $settleEntry = $this->ledger->getById(LedgerEntryId::fromString(self::SETTLE_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Settle, $settleEntry->type());
        $this->assertSame(200, $settleEntry->amount()->amount());
        $this->assertSame(700, $settleEntry->balanceAfter()->amount());
    }

    public function testReserveIsIdempotentForSameEntryId(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));

        ($this->reserve)(new ReserveWalletCommand(self::WALLET_ID, self::RESERVE_ENTRY_ID, 400));
        ($this->reserve)(new ReserveWalletCommand(self::WALLET_ID, self::RESERVE_ENTRY_ID, 400));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(600, $wallet->availableBalance()->amount());
        $this->assertSame(400, $wallet->reservedBalance()->amount());
        $this->assertCount(2, $this->ledger->findByWalletId(WalletId::fromString(self::WALLET_ID)));
    }

    public function testReserveFailsWhenInsufficientAvailable(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 100));

        $this->expectException(InsufficientFundsException::class);

        ($this->reserve)(new ReserveWalletCommand(self::WALLET_ID, self::RESERVE_ENTRY_ID, 200));
    }

    public function testReserveSameEntryIdWithDifferentAmountConflicts(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));
        ($this->reserve)(new ReserveWalletCommand(self::WALLET_ID, self::RESERVE_ENTRY_ID, 400));

        $this->expectException(LedgerIdempotencyConflictException::class);

        ($this->reserve)(new ReserveWalletCommand(self::WALLET_ID, self::RESERVE_ENTRY_ID, 200));
    }
}
