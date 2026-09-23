<?php

namespace App\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidMoneyException;

final readonly class Money
{
    private function __construct(
        private int $amount,
        private Currency $currency,
    ) {
    }

    public static function of(int $amount, Currency $currency): self
    {
        if ($amount < 0) {
            throw InvalidMoneyException::negativeAmount();
        }

        return new self($amount, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch();
        }

        return self::of($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch();
        }

        return self::of($this->amount - $other->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function greaterThan(self $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch();
        }

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch();
        }

        return $this->amount < $other->amount;
    }
}
