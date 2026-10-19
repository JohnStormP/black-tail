<?php

namespace App\Risk\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidRiskEvaluationException extends DomainException
{
    public static function negativeAmount(): self
    {
        return new self('Risk evaluation amount cannot be negative');
    }

    public static function negativeDailyDepositTotal(): self
    {
        return new self('Risk evaluation daily deposit total cannot be negative');
    }

    public static function negativeDailyBettingTotal(): self
    {
        return new self('Risk evaluation daily betting total cannot be negative');
    }

    public static function negativeBetsInWindow(): self
    {
        return new self('Risk evaluation bets in window cannot be negative');
    }
}
