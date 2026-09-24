<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidWalletIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Wallet id cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid wallet id format: '.$value);
    }
}
