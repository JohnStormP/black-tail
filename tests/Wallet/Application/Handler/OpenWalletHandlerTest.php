<?php

namespace App\Tests\Wallet\Application\Handler;

use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InvalidWalletIdException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OpenWalletHandlerTest extends KernelTestCase
{
    private OpenWalletHandler $handler;
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

        $this->handler = new OpenWalletHandler($this->wallets);
    }

    public function testOpensWallet(): void
    {
        $walletId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $playerId = '01BX5ZZKBKACTAV9WEVGEMMVRY';

        ($this->handler)(new OpenWalletCommand($walletId, $playerId, 'USD'));

        $wallet = $this->wallets->getById(WalletId::fromString($walletId));
        $this->assertTrue($wallet->playerId()->equals(PlayerId::fromString($playerId)));
        $this->assertSame(Currency::Usd, $wallet->currency());
        $this->assertSame(0, $wallet->availableBalance()->amount());
        $this->assertTrue($wallet->isActive());
    }

    public function testRejectsInvalidWalletId(): void
    {
        $this->expectException(InvalidWalletIdException::class);

        ($this->handler)(new OpenWalletCommand(
            'not-a-ulid',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'USD',
        ));
    }

    public function testRejectsInvalidCurrency(): void
    {
        $this->expectException(\ValueError::class);

        ($this->handler)(new OpenWalletCommand(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'BTC',
        ));
    }
}
