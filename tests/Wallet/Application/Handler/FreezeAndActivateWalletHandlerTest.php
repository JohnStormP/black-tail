<?php

namespace App\Tests\Wallet\Application\Handler;

use App\Wallet\Application\Command\ActivateWalletCommand;
use App\Wallet\Application\Command\CreditWalletCommand;
use App\Wallet\Application\Command\FreezeWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\ActivateWalletHandler;
use App\Wallet\Application\Handler\CreditWalletHandler;
use App\Wallet\Application\Handler\FreezeWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\WalletNotActiveException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use App\Wallet\Domain\ValueObject\WalletStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FreezeAndActivateWalletHandlerTest extends KernelTestCase
{
    private const string WALLET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private OpenWalletHandler $open;
    private FreezeWalletHandler $freeze;
    private ActivateWalletHandler $activate;
    private CreditWalletHandler $credit;
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
        $this->freeze = new FreezeWalletHandler($this->wallets);
        $this->activate = new ActivateWalletHandler($this->wallets);
        $this->credit = new CreditWalletHandler($this->wallets);
    }

    public function testFreezeThenActivate(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        ($this->freeze)(new FreezeWalletCommand(self::WALLET_ID));
        $frozen = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(WalletStatus::Frozen, $frozen->status());
        $this->assertFalse($frozen->isActive());

        ($this->activate)(new ActivateWalletCommand(self::WALLET_ID));
        $active = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(WalletStatus::Active, $active->status());
        $this->assertTrue($active->isActive());
    }

    public function testCreditFailsWhileFrozenAndWorksAfterActivate(): void
    {
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($this->freeze)(new FreezeWalletCommand(self::WALLET_ID));

        try {
            ($this->credit)(new CreditWalletCommand(self::WALLET_ID, 100));
            $this->fail('Expected WalletNotActiveException while frozen');
        } catch (WalletNotActiveException) {
        }

        ($this->activate)(new ActivateWalletCommand(self::WALLET_ID));
        ($this->credit)(new CreditWalletCommand(self::WALLET_ID, 100));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(100, $wallet->availableBalance()->amount());
    }

    public function testFreezeUnknownWalletFails(): void
    {
        $this->expectException(WalletNotFoundException::class);

        ($this->freeze)(new FreezeWalletCommand('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
