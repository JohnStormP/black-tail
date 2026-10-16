<?php

namespace App\Tests\Payment\Infrastructure\Doctrine;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePaymentRepositoryTest extends KernelTestCase
{
    private const string PAYMENT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';
    private const string WALLET_ID = '01DXYZ00000000000000000002';
    private const string IDEMPOTENCY_KEY = 'dep-01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private PaymentRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(PaymentRepositoryInterface::class);
        self::assertInstanceOf(PaymentRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Payment::class)->execute();
    }

    public function testSaveFindByIdAndFindByIdempotencyKey(): void
    {
        $id = PaymentId::fromString(self::PAYMENT_ID);
        $key = PaymentIdempotencyKey::fromString(self::IDEMPOTENCY_KEY);

        $this->repository->save($this->newPayment($id, $key));

        $byId = $this->repository->findById($id);
        $this->assertNotNull($byId);
        $this->assertTrue($byId->id()->equals($id));
        $this->assertTrue($byId->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertTrue($byId->playerId()->equals(PlayerId::fromString(self::PLAYER_ID)));
        $this->assertTrue($byId->walletId()->equals(WalletId::fromString(self::WALLET_ID)));
        $this->assertSame(PaymentType::Deposit, $byId->type());
        $this->assertSame(2500, $byId->amount()->amount());
        $this->assertSame(Currency::Usd, $byId->amount()->currency());
        $this->assertSame(PaymentStatus::Pending, $byId->status());
        $this->assertSame('fake', $byId->provider());
        $this->assertSame('prov-ref-1', $byId->providerReference());

        $byKey = $this->repository->findByIdempotencyKey($key);
        $this->assertNotNull($byKey);
        $this->assertTrue($byKey->id()->equals($id));

        $loaded = $this->repository->getById($id);
        $this->assertTrue($loaded->id()->equals($id));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        $this->repository->getById(PaymentId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }

    public function testFindByIdempotencyKeyReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->findByIdempotencyKey(
            PaymentIdempotencyKey::fromString('missing-key'),
        ));
    }

    private function newPayment(PaymentId $id, PaymentIdempotencyKey $key): Payment
    {
        return Payment::initiate(
            $id,
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            PaymentType::Deposit,
            Money::of(2500, Currency::Usd),
            $key,
            'fake',
            'prov-ref-1',
        );
    }
}
