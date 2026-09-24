<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidLedgerEntryIdException extends DomainException
{
    public static function empty(): self
    {
        return new self('Ledger entry id cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self('Invalid ledger entry id format: '.$value);
    }
}
