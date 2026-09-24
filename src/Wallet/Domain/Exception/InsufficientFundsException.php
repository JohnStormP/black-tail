<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InsufficientFundsException extends DomainException
{
    public static function forDebit(): self
    {
        return new self('Insufficient funds for debit');
    }
}
