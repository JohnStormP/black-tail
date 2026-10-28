<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Entity\Game;

final class GameJson
{
    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     status: string,
     *     createdAt: string,
     * }
     */
    public static function from(Game $game): array
    {
        return [
            'id' => $game->id()->toString(),
            'code' => $game->code()->toString(),
            'name' => $game->name(),
            'status' => $game->status()->value,
            'createdAt' => $game->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
