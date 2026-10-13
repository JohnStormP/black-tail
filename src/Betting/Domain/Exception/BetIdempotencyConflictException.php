<?php

namespace App\Betting\Domain\Exception;

use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use App\Shared\Domain\Exception\DomainException;

final class BetIdempotencyConflictException extends DomainException
{
    public static function forKey(BetIdempotencyKey $key): self
    {
        return new self('Bet with idempotency key "'.$key->toString().'" conflicts with the request');
    }
}
