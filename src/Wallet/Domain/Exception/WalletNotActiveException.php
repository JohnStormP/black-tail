<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class WalletNotActiveException extends DomainException
{
    public static function cannotCredit(): self
    {
        return new self('Cannot credit an inactive (frozen) wallet');
    }

    public static function cannotDebit(): self
    {
        return new self('Cannot debit an inactive (frozen) wallet');
    }
}
