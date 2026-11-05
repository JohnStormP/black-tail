<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameRoundId;
use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\WalletId;

final class InvalidGameRoundException extends DomainException
{
    public static function betNotAllowed(int $betMinor): self
    {
        return new self('Bet amount '.$betMinor.' is not allowed for this game');
    }

    public static function walletPlayerMismatch(GameRoundId $roundId, WalletId $walletId): self
    {
        return new self(
            'Wallet "'.$walletId->toString().'" does not belong to the player of game round "'.$roundId->toString().'"'
        );
    }

    public static function walletCurrencyMismatch(GameRoundId $roundId, WalletId $walletId): self
    {
        return new self(
            'Wallet "'.$walletId->toString().'" currency does not match game round "'.$roundId->toString().'"'
        );
    }

    public static function walletNotActive(WalletId $walletId): self
    {
        return new self('Wallet "'.$walletId->toString().'" is not active');
    }

    public static function payoutRequiresEntryId(GameRoundId $roundId): self
    {
        return new self(
            'Game round "'.$roundId->toString().'" has a win and requires a payout ledger entry id'
        );
    }

    public static function roundPayloadConflict(GameRoundId $roundId): self
    {
        return new self(
            'Game round with id "'.$roundId->toString().'" already exists with a different payload'
        );
    }
}
