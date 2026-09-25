<?php

namespace App\Tests\Wallet\Application\Handler;

use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Command\WithdrawWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Application\Handler\WithdrawWalletHandler;
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

final class DepositAndWithdrawWalletHandlerTest extends KernelTestCase
{
    private const string WALLET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string DEPOSIT_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string WITHDRAW_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';

    private OpenWalletHandler $open;
    private DepositWalletHandler $deposit;
    private WithdrawWalletHandler $withdraw;
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
        $this->withdraw = new WithdrawWalletHandler($this->wallets, $this->ledger, $transactions);
    }

    public function testDepositThenWithdraw(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));
        $afterDeposit = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $afterDeposit->availableBalance()->amount());

        $depositEntry = $this->ledger->getById(LedgerEntryId::fromString(self::DEPOSIT_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Deposit, $depositEntry->type());
        $this->assertSame(1000, $depositEntry->amount()->amount());
        $this->assertSame(1000, $depositEntry->balanceAfter()->amount());

        ($this->withdraw)(new WithdrawWalletCommand(self::WALLET_ID, self::WITHDRAW_ENTRY_ID, 400));
        $afterWithdraw = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(600, $afterWithdraw->availableBalance()->amount());

        $withdrawEntry = $this->ledger->getById(LedgerEntryId::fromString(self::WITHDRAW_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Withdrawal, $withdrawEntry->type());
        $this->assertSame(400, $withdrawEntry->amount()->amount());
        $this->assertSame(600, $withdrawEntry->balanceAfter()->amount());
    }

    public function testDepositIsIdempotentForSameEntryId(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $wallet->availableBalance()->amount());
        $this->assertCount(1, $this->ledger->findByWalletId(WalletId::fromString(self::WALLET_ID)));
    }

    public function testWithdrawFailsWhenInsufficientFunds(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 100));

        $this->expectException(InsufficientFundsException::class);

        ($this->withdraw)(new WithdrawWalletCommand(self::WALLET_ID, self::WITHDRAW_ENTRY_ID, 200));
    }

    public function testDepositSameEntryIdWithDifferentAmountConflicts(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 1000));

        $this->expectException(LedgerIdempotencyConflictException::class);

        ($this->deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 500));
    }
}
