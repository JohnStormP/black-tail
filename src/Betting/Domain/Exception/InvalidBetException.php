<?php

namespace App\Betting\Domain\Exception;

use App\Betting\Domain\ValueObject\BetId;
use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\WalletId;

final class InvalidBetException extends DomainException
{
    public static function nonPositiveAmount(): self
    {
        return new self('Bet amount must be greater than zero');
    }

    public static function walletPlayerMismatch(BetId $betId, WalletId $walletId): self
    {
        return new self('Wallet "'.$walletId->toString().'" does not belong to the player of bet "'.$betId->toString().'"');
    }

    public static function walletCurrencyMismatch(BetId $betId, WalletId $walletId): self
    {
        return new self('Wallet "'.$walletId->toString().'" currency does not match bet "'.$betId->toString().'"');
    }
}
