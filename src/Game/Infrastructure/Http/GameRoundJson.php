<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Entity\GameRound;

final class GameRoundJson
{
    /**
     * @return array{
     *     id: string,
     *     gameId: string,
     *     playerId: string,
     *     betMinor: int,
     *     stops: list<int>,
     *     totalMultiplier: int,
     *     winMinor: int,
     *     isWin: bool,
     *     createdAt: string,
     * }
     */
    public static function from(GameRound $round): array
    {
        return [
            'id' => $round->id()->toString(),
            'gameId' => $round->gameId()->toString(),
            'playerId' => $round->playerId()->toString(),
            'betMinor' => $round->betMinor(),
            'stops' => $round->stops(),
            'totalMultiplier' => $round->totalMultiplier(),
            'winMinor' => $round->winMinor(),
            'isWin' => $round->isWin(),
            'createdAt' => $round->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
