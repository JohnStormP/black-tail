<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Entity\TenantGame;

final class TenantGameJson
{
    /**
     * @return array{
     *     id: string,
     *     tenantId: string,
     *     gameId: string,
     *     enabled: bool,
     *     createdAt: string,
     * }
     */
    public static function from(TenantGame $link): array
    {
        return [
            'id' => $link->id()->toString(),
            'tenantId' => $link->tenantId()->toString(),
            'gameId' => $link->gameId()->toString(),
            'enabled' => $link->isEnabled(),
            'createdAt' => $link->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
