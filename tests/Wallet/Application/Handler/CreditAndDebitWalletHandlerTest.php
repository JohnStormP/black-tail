<?php

namespace App\Tests\Wallet\Application\Handler;

use App\Wallet\Application\Command\CreditWalletCommand;
use App\Wallet\Application\Command\DebitWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\CreditWalletHandler;
use App\Wallet\Application\Handler\DebitWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Exception\WalletNotActiveException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreditAndDebitWalletHandlerTest extends KernelTestCase
{
    private const WALLET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private OpenWalletHandler $open;
    private CreditWalletHandler $credit;
    private DebitWalletHandler $debit;
    private WalletRepositoryInterface $wallets;

    protected function setUp(): void
    {
        self::bootKernel();

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();

        $this->open = new OpenWalletHandler($this->wallets);
        $this->credit = new CreditWalletHandler($this->wallets);
        $this->debit = new DebitWalletHandler($this->wallets);
    }

    public function testCreditThenDebit(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, 1000));
        $afterCredit = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $afterCredit->availableBalance()->amount());

        ($this->debit)(new DebitWalletCommand(self::WALLET_ID, 400));
        $afterDebit = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(600, $afterDebit->availableBalance()->amount());
    }

    public function testDebitFailsWhenInsufficientFunds(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, 100));

        $this->expectException(InsufficientFundsException::class);

        ($this->debit)(new DebitWalletCommand(self::WALLET_ID, 200));
    }

    public function testCreditFailsWhenWalletFrozen(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $wallet->freeze();
        $this->wallets->save($wallet);

        $this->expectException(WalletNotActiveException::class);

        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, 100));
    }

    public function testCreditUnknownWalletFails(): void
    {
        $this->expectException(WalletNotFoundException::class);

        ($this->credit)(new CreditWalletCommand('01BX5ZZKBKACTAV9WEVGEMMVRY', 100));
    }
}
