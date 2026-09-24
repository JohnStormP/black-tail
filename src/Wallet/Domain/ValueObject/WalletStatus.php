<?php

namespace App\Wallet\Domain\ValueObject;

enum WalletStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
}
