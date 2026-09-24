<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidLedgerEntryException extends DomainException
{
    public static function zeroAmount(): self
    {
        return new self('Ledger entry amount must be greater than zero');
    }

    public static function currencyMismatch(): self
    {
        return new self('Ledger entry amount and balance after must use the same currency');
    }
}
