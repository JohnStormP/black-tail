<?php

namespace App\Player\Infrastructure\Http;

use App\Player\Domain\Entity\Player;

final class PlayerJson
{
    /**
     * @return array{
     *     id: string,
     *     tenantId: string,
     *     email: string,
     *     status: string,
     *     createdAt: string,
     * }
     */
    public static function from(Player $player): array
    {
        return [
            'id' => $player->id()->toString(),
            'tenantId' => $player->tenantId()->toString(),
            'email' => $player->email()->toString(),
            'status' => $player->status()->value,
            'createdAt' => $player->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
