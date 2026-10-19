<?php

namespace App\Risk\Domain\ValueObject;

enum RiskRuleType: string
{
    case DailyDepositLimit = 'daily_deposit_limit';
    case DailyBettingLimit = 'daily_betting_limit';
    case MaxBet = 'max_bet';
    case MaxWithdrawal = 'max_withdrawal';
    case BetVelocity = 'bet_velocity';
}
