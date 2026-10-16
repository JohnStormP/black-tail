<?php

namespace App\Payment\Infrastructure\Doctrine;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PaymentRepositoryInterface::class, public: true)]
final class DoctrinePaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Payment $payment): void
    {
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(PaymentId $id): ?Payment
    {
        return $this->entityManager->find(Payment::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(PaymentId $id): Payment
    {
        $payment = $this->findById($id);

        if (null === $payment) {
            throw PaymentNotFoundException::withId($id);
        }

        return $payment;
    }

    public function findByIdempotencyKey(PaymentIdempotencyKey $key): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)->findOneBy([
            'idempotencyKey' => $key->toString(),
        ]);
    }
}
