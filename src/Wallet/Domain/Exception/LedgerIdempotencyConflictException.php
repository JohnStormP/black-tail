<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\LedgerEntryId;

final class LedgerIdempotencyConflictException extends DomainException
{
    public static function forEntry(LedgerEntryId $id): self
    {
        return new self('Ledger entry with id "'.$id->toString().'" conflicts with the request');
    }
}
