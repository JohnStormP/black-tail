<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidMoneyException extends DomainException
{
    public static function negativeAmount(): self
    {
        return new self('Money amount cannot be negative');
    }

    public static function currencyMismatch(): self
    {
        return new self('Cannot operate on money with different currencies');
    }
}
