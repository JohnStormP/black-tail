<?php

namespace App\Payment\Domain\ValueObject;

enum PaymentType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
}
