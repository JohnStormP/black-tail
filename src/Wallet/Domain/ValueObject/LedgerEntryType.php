<?php

namespace App\Wallet\Domain\ValueObject;

enum LedgerEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Reserve = 'reserve';
    case Release = 'release';
    case Settle = 'settle';
}
