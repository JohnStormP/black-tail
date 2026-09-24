<?php

namespace App\Tests\Wallet\Infrastructure\Doctrine;

use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineWalletRepositoryTest extends KernelTestCase
{
    private WalletRepositoryInterface $repository;
    private TransactionManagerInterface $transactions;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);
        $this->transactions = $transactions;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $walletId = WalletId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $playerId = PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $wallet = Wallet::open($walletId, $playerId, Currency::Usd);

        $this->repository->save($wallet);

        $found = $this->repository->findById($walletId);

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($walletId));
        $this->assertTrue($found->playerId()->equals($playerId));
        $this->assertSame(Currency::Usd, $found->currency());
        $this->assertSame(0, $found->availableBalance()->amount());
        $this->assertTrue($found->isActive());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(WalletNotFoundException::class);

        $this->repository->getById(WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }

    public function testGetByIdForUpdateInsideTransaction(): void
    {
        $walletId = WalletId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $this->repository->save(Wallet::open(
            $walletId,
            PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            Currency::Usd,
        ));

        $locked = $this->transactions->transactional(
            fn (): Wallet => $this->repository->getByIdForUpdate($walletId),
        );

        $this->assertTrue($locked->id()->equals($walletId));
    }

    public function testGetByIdForUpdateThrowsWhenMissing(): void
    {
        $this->expectException(WalletNotFoundException::class);

        $this->transactions->transactional(function (): void {
            $this->repository->getByIdForUpdate(WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
        });
    }
}
