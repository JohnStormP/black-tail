<?php

namespace App\Tests\Wallet\Application\Handler;

use App\Wallet\Application\Command\CreditWalletCommand;
use App\Wallet\Application\Command\DebitWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\CreditWalletHandler;
use App\Wallet\Application\Handler\DebitWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Exception\WalletNotActiveException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreditAndDebitWalletHandlerTest extends KernelTestCase
{
    private const string WALLET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string CREDIT_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F01';
    private const string DEBIT_ENTRY_ID = '01ARZ3NDEKTSV4RRFFQ69G5F02';
    private const string CREDIT_ENTRY_ID_2 = '01ARZ3NDEKTSV4RRFFQ69G5F03';

    private OpenWalletHandler $open;
    private CreditWalletHandler $credit;
    private DebitWalletHandler $debit;
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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();

        $this->open = new OpenWalletHandler($this->wallets);
        $this->credit = new CreditWalletHandler($this->wallets, $this->ledger);
        $this->debit = new DebitWalletHandler($this->wallets, $this->ledger);
    }

    public function testCreditThenDebitWritesLedger(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, self::CREDIT_ENTRY_ID, 1000));
        $afterCredit = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $afterCredit->availableBalance()->amount());

        $creditEntry = $this->ledger->getById(LedgerEntryId::fromString(self::CREDIT_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Credit, $creditEntry->type());
        $this->assertSame(1000, $creditEntry->amount()->amount());
        $this->assertSame(1000, $creditEntry->balanceAfter()->amount());

        ($this->debit)(new DebitWalletCommand(self::WALLET_ID, self::DEBIT_ENTRY_ID, 400));
        $afterDebit = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(600, $afterDebit->availableBalance()->amount());

        $debitEntry = $this->ledger->getById(LedgerEntryId::fromString(self::DEBIT_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Debit, $debitEntry->type());
        $this->assertSame(400, $debitEntry->amount()->amount());
        $this->assertSame(600, $debitEntry->balanceAfter()->amount());

        $history = $this->ledger->findByWalletId(WalletId::fromString(self::WALLET_ID));
        $this->assertCount(2, $history);
        $this->assertTrue($history[0]->id()->equals(LedgerEntryId::fromString(self::DEBIT_ENTRY_ID)));
        $this->assertTrue($history[1]->id()->equals(LedgerEntryId::fromString(self::CREDIT_ENTRY_ID)));
    }

    public function testDebitFailsWhenInsufficientFundsAndDoesNotWriteLedger(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, self::CREDIT_ENTRY_ID, 100));

        try {
            ($this->debit)(new DebitWalletCommand(self::WALLET_ID, self::DEBIT_ENTRY_ID, 200));
            $this->fail('Expected InsufficientFundsException');
        } catch (InsufficientFundsException) {
        }

        $this->assertNull($this->ledger->findById(LedgerEntryId::fromString(self::DEBIT_ENTRY_ID)));
        $this->assertCount(1, $this->ledger->findByWalletId(WalletId::fromString(self::WALLET_ID)));
    }

    public function testCreditFailsWhenWalletFrozen(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $wallet->freeze();
        $this->wallets->save($wallet);

        $this->expectException(WalletNotActiveException::class);

        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, self::CREDIT_ENTRY_ID, 100));
    }

    public function testCreditUnknownWalletFails(): void
    {
        $this->expectException(WalletNotFoundException::class);

        ($this->credit)(new CreditWalletCommand(
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            self::CREDIT_ENTRY_ID_2,
            100,
        ));
    }
}
