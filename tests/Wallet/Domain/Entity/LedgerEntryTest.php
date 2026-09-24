<?php

namespace App\Tests\Wallet\Domain\Entity;

use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\InvalidLedgerEntryException;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;

final class LedgerEntryTest extends TestCase
{
    public function testRecordCredit(): void
    {
        $entryId = LedgerEntryId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $walletId = WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $entry = LedgerEntry::record(
            $entryId,
            $walletId,
            LedgerEntryType::Credit,
            Money::of(1000, Currency::Usd),
            Money::of(1000, Currency::Usd),
            $createdAt,
        );

        $this->assertTrue($entry->id()->equals($entryId));
        $this->assertTrue($entry->walletId()->equals($walletId));
        $this->assertSame(LedgerEntryType::Credit, $entry->type());
        $this->assertTrue($entry->isCredit());
        $this->assertFalse($entry->isDebit());
        $this->assertTrue($entry->amount()->equals(Money::of(1000, Currency::Usd)));
        $this->assertTrue($entry->balanceAfter()->equals(Money::of(1000, Currency::Usd)));
        $this->assertSame(Currency::Usd, $entry->currency());
        $this->assertEquals($createdAt, $entry->createdAt());
    }

    public function testRecordDebit(): void
    {
        $entry = LedgerEntry::record(
            LedgerEntryId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            LedgerEntryType::Debit,
            Money::of(400, Currency::Usd),
            Money::of(600, Currency::Usd),
        );

        $this->assertSame(LedgerEntryType::Debit, $entry->type());
        $this->assertTrue($entry->isDebit());
        $this->assertFalse($entry->isCredit());
        $this->assertSame(400, $entry->amount()->amount());
        $this->assertSame(600, $entry->balanceAfter()->amount());
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(InvalidLedgerEntryException::class);

        LedgerEntry::record(
            LedgerEntryId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            LedgerEntryType::Credit,
            Money::of(0, Currency::Usd),
            Money::of(0, Currency::Usd),
        );
    }

    public function testRejectsCurrencyMismatch(): void
    {
        $this->expectException(InvalidLedgerEntryException::class);

        LedgerEntry::record(
            LedgerEntryId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            LedgerEntryType::Credit,
            Money::of(100, Currency::Usd),
            Money::of(100, Currency::Eur),
        );
    }
}
