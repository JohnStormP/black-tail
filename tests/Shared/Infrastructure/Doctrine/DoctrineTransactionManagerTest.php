<?php

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTransactionManagerTest extends KernelTestCase
{
    public function testRollsBackWalletSaveOnException(): void
    {
        self::bootKernel();

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();

        $walletId = WalletId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $exception = null;
        try {
            $transactions->transactional(function () use ($wallets, $walletId): void {
                $wallets->save(Wallet::open(
                    $walletId,
                    PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
                    Currency::Usd,
                ));

                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException $caught) {
            $exception = $caught;
        }

        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('force rollback', $exception->getMessage());
        self::assertTrue($entityManager->isOpen());
        self::assertNull($wallets->findById($walletId));
    }
}
