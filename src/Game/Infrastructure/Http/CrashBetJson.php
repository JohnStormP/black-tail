<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Entity\CrashBet;

final class CrashBetJson
{
    /**
     * @return array{
     *     id: string,
     *     roundId: string,
     *     playerId: string,
     *     walletId: string,
     *     amountMinor: int,
     *     currency: string,
     *     status: string,
     *     cashOutHundredths: int|null,
     *     payoutMinor: int|null,
     *     createdAt: string,
     * }
     */
    public static function from(CrashBet $bet): array
    {
        return [
            'id' => $bet->id()->toString(),
            'roundId' => $bet->roundId()->toString(),
            'playerId' => $bet->playerId()->toString(),
            'walletId' => $bet->walletId()->toString(),
            'amountMinor' => $bet->amount()->amount(),
            'currency' => $bet->amount()->currency()->value,
            'status' => $bet->status()->value,
            'cashOutHundredths' => $bet->cashOutMultiplier()?->toHundredths(),
            'payoutMinor' => $bet->payoutMinor(),
            'createdAt' => $bet->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
