<?php

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\WalletId;

final class InvalidPaymentException extends DomainException
{
    public static function nonPositiveAmount(): self
    {
        return new self('Payment amount must be greater than zero');
    }

    public static function emptyProvider(): self
    {
        return new self('Payment provider cannot be empty');
    }

    public static function emptyFailureReason(): self
    {
        return new self('Payment failure reason cannot be empty');
    }

    public static function playerNotActive(PlayerId $playerId): self
    {
        return new self('Player with id "'.$playerId->toString().'" is not active and cannot initiate a payment');
    }

    public static function walletNotActive(WalletId $walletId): self
    {
        return new self('Wallet with id "'.$walletId->toString().'" is not active and cannot be used for a payment');
    }

    public static function walletPlayerMismatch(PaymentId $paymentId, WalletId $walletId): self
    {
        return new self('Wallet "'.$walletId->toString().'" does not belong to the player of payment "'.$paymentId->toString().'"');
    }

    public static function walletCurrencyMismatch(PaymentId $paymentId, WalletId $walletId): self
    {
        return new self('Wallet "'.$walletId->toString().'" currency does not match payment "'.$paymentId->toString().'"');
    }
}
