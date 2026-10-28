<?php

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Tenant\Domain\ValueObject\TenantId;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findById(PaymentId $id): ?Payment;

    /**
     * @throws PaymentNotFoundException
     */
    public function getById(PaymentId $id): Payment;

    public function findByIdempotencyKey(PaymentIdempotencyKey $key): ?Payment;

    public function findByProviderReference(string $provider, string $providerReference): ?Payment;

    /**
     * @throws PaymentNotFoundException
     */
    public function getByIdForUpdate(PaymentId $id): Payment;

    /**
     * @return list<Payment>
     */
    public function findByTenantId(TenantId $tenantId, ?PaymentType $type = null): array;
}
