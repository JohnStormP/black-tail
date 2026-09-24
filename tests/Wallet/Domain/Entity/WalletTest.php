<?php

namespace App\Tests\Wallet\Domain\Entity;

use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Exception\InvalidMoneyException;
use App\Wallet\Domain\Exception\WalletNotActiveException;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use App\Wallet\Domain\ValueObject\WalletStatus;
use PHPUnit\Framework\TestCase;

final class WalletTest extends TestCase
{
    public function testOpenCreatesActiveWalletWithZeroBalance(): void
    {
        $walletId = WalletId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $playerId = PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $wallet = Wallet::open($walletId, $playerId, Currency::Usd, $createdAt);

        $this->assertTrue($wallet->id()->equals($walletId));
        $this->assertTrue($wallet->playerId()->equals($playerId));
        $this->assertSame(Currency::Usd, $wallet->currency());
        $this->assertTrue($wallet->availableBalance()->isZero());
        $this->assertSame(WalletStatus::Active, $wallet->status());
        $this->assertTrue($wallet->isActive());
        $this->assertEquals($createdAt, $wallet->createdAt());
    }

    public function testCreditAndDebit(): void
    {
        $wallet = $this->openWallet();

        $wallet->credit(Money::of(1000, Currency::Usd));
        $this->assertTrue($wallet->availableBalance()->equals(Money::of(1000, Currency::Usd)));

        $wallet->debit(Money::of(400, Currency::Usd));
        $this->assertTrue($wallet->availableBalance()->equals(Money::of(600, Currency::Usd)));
    }

    public function testDebitRejectsInsufficientFunds(): void
    {
        $wallet = $this->openWallet();
        $wallet->credit(Money::of(100, Currency::Usd));

        $this->expectException(InsufficientFundsException::class);

        $wallet->debit(Money::of(200, Currency::Usd));
    }

    public function testCreditRejectsCurrencyMismatch(): void
    {
        $wallet = $this->openWallet();

        $this->expectException(InvalidMoneyException::class);

        $wallet->credit(Money::of(100, Currency::Eur));
    }

    public function testFrozenWalletRejectsCreditAndDebit(): void
    {
        $wallet = $this->openWallet();
        $wallet->credit(Money::of(500, Currency::Usd));
        $wallet->freeze();

        try {
            $wallet->credit(Money::of(100, Currency::Usd));
            $this->fail('Expected WalletNotActiveException on credit');
        } catch (WalletNotActiveException) {
        }

        $this->expectException(WalletNotActiveException::class);
        $wallet->debit(Money::of(100, Currency::Usd));
    }

    public function testActivateAfterFreezeAllowsCredit(): void
    {
        $wallet = $this->openWallet();
        $wallet->freeze();
        $wallet->activate();

        $wallet->credit(Money::of(100, Currency::Usd));

        $this->assertTrue($wallet->availableBalance()->equals(Money::of(100, Currency::Usd)));
    }

    private function openWallet(): Wallet
    {
        return Wallet::open(
            WalletId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            Currency::Usd,
        );
    }
}
