<?php

namespace App\Payment\Infrastructure\Http;

use App\Payment\Domain\Entity\Payment;

final class PaymentJson
{
    /**
     * @return array{
     *     id: string,
     *     tenantId: string,
     *     playerId: string,
     *     walletId: string,
     *     type: string,
     *     amountMinor: int,
     *     currency: string,
     *     status: string,
     *     idempotencyKey: string,
     *     provider: string,
     *     providerReference: string|null,
     *     failureReason: string|null,
     *     createdAt: string,
     * }
     */
    public static function from(Payment $payment): array
    {
        return [
            'id' => $payment->id()->toString(),
            'tenantId' => $payment->tenantId()->toString(),
            'playerId' => $payment->playerId()->toString(),
            'walletId' => $payment->walletId()->toString(),
            'type' => $payment->type()->value,
            'amountMinor' => $payment->amount()->amount(),
            'currency' => $payment->amount()->currency()->value,
            'status' => $payment->status()->value,
            'idempotencyKey' => $payment->idempotencyKey()->toString(),
            'provider' => $payment->provider(),
            'providerReference' => $payment->providerReference(),
            'failureReason' => $payment->failureReason(),
            'createdAt' => $payment->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
