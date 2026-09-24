<?php

namespace App\Wallet\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Wallet\Domain\ValueObject\WalletId;

final class WalletNotFoundException extends DomainException
{
    public static function withId(WalletId $id): self
    {
        return new self('Wallet with id "'.$id->toString().'" not found');
    }
}
