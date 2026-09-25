<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InsufficientFundsException extends DomainException
{
    public static function forDebit(): self
    {
        return new self('Insufficient funds for debit');
    }

    public static function forReserve(): self
    {
        return new self('Insufficient available funds for reserve');
    }

    public static function forRelease(): self
    {
        return new self('Insufficient reserved funds for release');
    }

    public static function forSettle(): self
    {
        return new self('Insufficient reserved funds for settle');
    }
}
