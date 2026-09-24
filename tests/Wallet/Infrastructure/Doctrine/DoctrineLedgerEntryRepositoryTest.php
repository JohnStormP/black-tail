<?php

namespace App\Tests\Wallet\Infrastructure\Doctrine;

use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerEntryNotFoundException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineLedgerEntryRepositoryTest extends KernelTestCase
{
    private const string WALLET_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string ENTRY_ID_1 = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string ENTRY_ID_2 = '01ARZ3NDEKTSV4RRFFQ69G5FB0';

    private LedgerEntryRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $entryId = LedgerEntryId::fromString(self::ENTRY_ID_1);
        $walletId = WalletId::fromString(self::WALLET_ID);
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $entry = LedgerEntry::record(
            $entryId,
            $walletId,
            LedgerEntryType::Credit,
            Money::of(1000, Currency::Usd),
            Money::of(1000, Currency::Usd),
            $createdAt,
        );

        $this->repository->save($entry);

        $found = $this->repository->findById($entryId);

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($entryId));
        $this->assertTrue($found->walletId()->equals($walletId));
        $this->assertSame(LedgerEntryType::Credit, $found->type());
        $this->assertSame(1000, $found->amount()->amount());
        $this->assertSame(1000, $found->balanceAfter()->amount());
        $this->assertSame(Currency::Usd, $found->currency());
        $this->assertEquals($createdAt, $found->createdAt());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(LedgerEntryNotFoundException::class);

        $this->repository->getById(LedgerEntryId::fromString(self::ENTRY_ID_2));
    }

    public function testFindByWalletIdReturnsNewestFirst(): void
    {
        $walletId = WalletId::fromString(self::WALLET_ID);

        $older = LedgerEntry::record(
            LedgerEntryId::fromString(self::ENTRY_ID_1),
            $walletId,
            LedgerEntryType::Credit,
            Money::of(1000, Currency::Usd),
            Money::of(1000, Currency::Usd),
            new \DateTimeImmutable('2026-01-15 12:00:00'),
        );
        $newer = LedgerEntry::record(
            LedgerEntryId::fromString(self::ENTRY_ID_2),
            $walletId,
            LedgerEntryType::Debit,
            Money::of(400, Currency::Usd),
            Money::of(600, Currency::Usd),
            new \DateTimeImmutable('2026-01-15 13:00:00'),
        );

        $this->repository->save($older);
        $this->repository->save($newer);

        $entries = $this->repository->findByWalletId($walletId);

        $this->assertCount(2, $entries);
        $this->assertTrue($entries[0]->id()->equals(LedgerEntryId::fromString(self::ENTRY_ID_2)));
        $this->assertTrue($entries[1]->id()->equals(LedgerEntryId::fromString(self::ENTRY_ID_1)));
    }

    public function testFindByWalletIdReturnsEmptyListWhenNone(): void
    {
        $entries = $this->repository->findByWalletId(WalletId::fromString(self::WALLET_ID));

        $this->assertSame([], $entries);
    }
}
