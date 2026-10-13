<?php

namespace App\Game\Domain\Exception;

use App\Game\Domain\ValueObject\GameId;
use App\Shared\Domain\Exception\DomainException;
use App\Tenant\Domain\ValueObject\TenantId;

final class GameNotPlayableException extends DomainException
{
    public static function disabled(GameId $id): self
    {
        return new self('Game with id "'.$id->toString().'" is disabled and cannot be played');
    }

    public static function notAssignedToTenant(GameId $gameId, TenantId $tenantId): self
    {
        return new self('Game with id "'.$gameId->toString().'" is not assigned to tenant "'.$tenantId->toString().'"');
    }

    public static function disabledForTenant(GameId $gameId, TenantId $tenantId): self
    {
        return new self('Game with id "'.$gameId->toString().'" is disabled for tenant "'.$tenantId->toString().'"');
    }
}
