<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\TenantGameId;
use App\Shared\Domain\Exception\DomainException;

final class TenantGameNotFoundException extends DomainException
{
    public static function withId(TenantGameId $id): self
    {
        return new self('Tenant game with id "'.$id->toString().'" cannot be found');
    }
}
