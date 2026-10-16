<?php

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findById(PaymentId $id): ?Payment;

    /**
     * @throws PaymentNotFoundException
     */
    public function getById(PaymentId $id): Payment;

    public function findByIdempotencyKey(PaymentIdempotencyKey $key): ?Payment;
}
