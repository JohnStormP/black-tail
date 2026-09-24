<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\LedgerEntryId;

final class LedgerEntryNotFoundException extends DomainException
{
    public static function withId(LedgerEntryId $id): self
    {
        return new self('Ledger entry with id "'.$id->toString().'" not found');
    }
}
