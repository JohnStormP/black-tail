<?php

namespace App\Wallet\Domain\ValueObject;

enum LedgerEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
