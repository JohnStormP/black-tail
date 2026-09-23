<?php

namespace App\Wallet\Domain\ValueObject;

enum Currency: string
{
    case Usd = 'USD';
    case Eur = 'EUR';
    case Uah = 'UAH';
}
