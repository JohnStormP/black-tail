<?php

namespace App\Tests\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidMoneyException;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testOfAcceptsZeroAndPositive(): void
    {
        $zero = Money::of(0, Currency::Usd);
        $money = Money::of(1050, Currency::Usd);

        $this->assertTrue($zero->isZero());
        $this->assertSame(1050, $money->amount());
        $this->assertSame(Currency::Usd, $money->currency());
    }

    public function testOfRejectsNegative(): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::of(-1, Currency::Usd);
    }

    public function testAdd(): void
    {
        $a = Money::of(1000, Currency::Usd);
        $b = Money::of(250, Currency::Usd);

        $sum = $a->add($b);

        $this->assertTrue($sum->equals(Money::of(1250, Currency::Usd)));
        $this->assertTrue($a->equals(Money::of(1000, Currency::Usd)));
    }

    public function testAddRejectsCurrencyMismatch(): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::of(100, Currency::Usd)->add(Money::of(100, Currency::Eur));
    }

    public function testSubtract(): void
    {
        $result = Money::of(1000, Currency::Usd)->subtract(Money::of(400, Currency::Usd));

        $this->assertTrue($result->equals(Money::of(600, Currency::Usd)));
    }

    public function testSubtractRejectsNegativeResult(): void
    {
        $this->expectException(InvalidMoneyException::class);

        Money::of(100, Currency::Usd)->subtract(Money::of(200, Currency::Usd));
    }

    public function testComparisons(): void
    {
        $a = Money::of(500, Currency::Usd);
        $b = Money::of(200, Currency::Usd);

        $this->assertTrue($a->greaterThan($b));
        $this->assertTrue($b->lessThan($a));
        $this->assertFalse($a->lessThan($b));
    }
}
