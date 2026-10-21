<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\WalletId;

final class InvalidCrashBetException extends DomainException
{
    public static function nonPositiveAmount(): self
    {
        return new self('Crash bet amount must be greater than zero');
    }

    public static function roundNotAcceptingBets(CrashRoundId $roundId): self
    {
        return new self('Crash round "'.$roundId->toString().'" is not accepting bets');
    }

    public static function cashOutNotAllowed(CrashBetId $betId, CrashMultiplier $at): self
    {
        return new self(
            'Cash out at '.$at->toHundredths().' hundredths is not allowed for crash bet "'.$betId->toString().'"'
        );
    }

    public static function walletPlayerMismatch(CrashBetId $betId, WalletId $walletId): self
    {
        return new self(
            'Wallet "'.$walletId->toString().'" does not belong to the player of crash bet "'.$betId->toString().'"'
        );
    }

    public static function walletCurrencyMismatch(CrashBetId $betId, WalletId $walletId): self
    {
        return new self(
            'Wallet "'.$walletId->toString().'" currency does not match crash bet "'.$betId->toString().'"'
        );
    }
}
