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

    public static function cannotReserve(): self
    {
        return new self('Cannot reserve on an inactive (frozen) wallet');
    }

    public static function cannotRelease(): self
    {
        return new self('Cannot release on an inactive (frozen) wallet');
    }

    public static function cannotSettle(): self
    {
        return new self('Cannot settle on an inactive (frozen) wallet');
    }

    public static function cannotCreditBonus(): self
    {
        return new self('Cannot credit bonus on an inactive (frozen) wallet');
    }

    public static function cannotUnlockBonus(): self
    {
        return new self('Cannot unlock bonus on an inactive (frozen) wallet');
    }

    public static function cannotForfeitBonus(): self
    {
        return new self('Cannot forfeit bonus on an inactive (frozen) wallet');
    }
}
