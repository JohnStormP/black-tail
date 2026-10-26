<?php

namespace App\Betting\Infrastructure\Http;

use App\Betting\Domain\Entity\Bet;

final class BetJson
{
    /**
     * @return array{
     *     id: string,
     *     playerId: string,
     *     gameId: string,
     *     roundId: string,
     *     amountMinor: int,
     *     currency: string,
     *     status: string,
     *     idempotencyKey: string,
     *     providerReference: string|null,
     *     createdAt: string,
     * }
     */
    public static function from(Bet $bet): array
    {
        return [
            'id' => $bet->id()->toString(),
            'playerId' => $bet->playerId()->toString(),
            'gameId' => $bet->gameId()->toString(),
            'roundId' => $bet->roundId()->toString(),
            'amountMinor' => $bet->amount()->amount(),
            'currency' => $bet->amount()->currency()->value,
            'status' => $bet->status()->value,
            'idempotencyKey' => $bet->idempotencyKey()->toString(),
            'providerReference' => $bet->providerReference(),
            'createdAt' => $bet->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
