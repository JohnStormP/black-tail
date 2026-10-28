<?php

namespace App\Payment\Infrastructure\Doctrine;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\DBAL\LockMode;
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

    public function findByProviderReference(string $provider, string $providerReference): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)->findOneBy([
            'provider' => trim($provider),
            'providerReference' => trim($providerReference),
        ]);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getByIdForUpdate(PaymentId $id): Payment
    {
        $payment = $this->entityManager->find(
            Payment::class,
            $id->toString(),
            LockMode::PESSIMISTIC_WRITE,
        );

        if (null === $payment) {
            throw PaymentNotFoundException::withId($id);
        }

        return $payment;
    }

    /**
     * @return list<Payment>
     */
    public function findByTenantId(TenantId $tenantId, ?PaymentType $type = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Payment::class, 'p')
            ->where('p.tenantId = :tenantId')
            ->setParameter('tenantId', $tenantId->toString())
            ->orderBy('p.createdAt', 'DESC');

        if (null !== $type) {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $type);
        }

        /** @var list<Payment> $payments */
        $payments = $qb->getQuery()->getResult();

        return $payments;
    }
}
