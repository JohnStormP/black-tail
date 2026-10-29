<?php

namespace App\Wallet\Infrastructure\Http;

use App\Wallet\Domain\Entity\Wallet;

final class WalletJson
{
    /**
     * @return array{
     *     id: string,
     *     playerId: string,
     *     currency: string,
     *     available: int,
     *     reserved: int,
     *     status: string,
     * }
     */
    public static function from(Wallet $wallet): array
    {
        return [
            'id' => $wallet->id()->toString(),
            'playerId' => $wallet->playerId()->toString(),
            'currency' => $wallet->currency()->value,
            'available' => $wallet->availableBalance()->amount(),
            'reserved' => $wallet->reservedBalance()->amount(),
            'status' => $wallet->status()->value,
        ];
    }
}
