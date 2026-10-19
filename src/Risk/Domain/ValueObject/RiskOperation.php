<?php

namespace App\Risk\Domain\ValueObject;

enum RiskOperation: string
{
    case Deposit = 'deposit';
    case Bet = 'bet';
    case Withdrawal = 'withdrawal';
}
