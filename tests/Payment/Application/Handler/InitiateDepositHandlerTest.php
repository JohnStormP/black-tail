<?php

namespace App\Tests\Payment\Application\Handler;

use App\Payment\Application\Command\InitiateDepositCommand;
use App\Payment\Application\Handler\InitiateDepositHandler;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\PaymentIdempotencyConflictException;
use App\Payment\Domain\Provider\PaymentProviderInitiationStatus;
use App\Payment\Domain\Provider\PaymentProviderInterface;
use App\Payment\Domain\Provider\PaymentProviderRequest;
use App\Payment\Domain\Provider\PaymentProviderResolver;
use App\Payment\Domain\Provider\PaymentProviderResult;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InitiateDepositHandlerTest extends KernelTestCase
{
    private const string PAYMENT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string WALLET_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string OTHER_WALLET_ID = '01EXYZ00000000000000000003';
    private const string OTHER_PLAYER_ID = '01FXYZ00000000000000000004';
    private const string IDEMPOTENCY_KEY = 'dep-req-001';

    private InitiateDepositHandler $handler;
    private PaymentRepositoryInterface $payments;
    private WalletRepositoryInterface $wallets;
    private PlayerRepositoryInterface $players;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $payments = self::getContainer()->get(PaymentRepositoryInterface::class);
        self::assertInstanceOf(PaymentRepositoryInterface::class, $payments);
        $this->payments = $payments;

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $this->players = $players;

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $entityManager->createQuery('DELETE FROM '.Payment::class)->execute();
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(TenantId::fromString(self::TENANT_ID), 'Casino Alpha'));
        $this->players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));
        $this->players->save(Player::register(
            PlayerId::fromString(self::OTHER_PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('other@example.com'),
        ));

        $open = new OpenWalletHandler($this->wallets);
        ($open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
        ($open)(new OpenWalletCommand(self::OTHER_WALLET_ID, self::OTHER_PLAYER_ID, 'USD'));

        $this->handler = new InitiateDepositHandler(
            $this->payments,
            $this->players,
            $this->wallets,
            new PaymentProviderResolver([new FakePaymentProvider()]),
        );
    }

    public function testInitiateCreatesProcessingDepositWithoutCreditingWallet(): void
    {
        ($this->handler)($this->command());

        $payment = $this->payments->getById(PaymentId::fromString(self::PAYMENT_ID));
        $this->assertSame(PaymentType::Deposit, $payment->type());
        $this->assertSame(PaymentStatus::Processing, $payment->status());
        $this->assertSame(2500, $payment->amount()->amount());
        $this->assertSame('fake', $payment->provider());
        $this->assertSame('fake-'.self::PAYMENT_ID, $payment->providerReference());
        $this->assertTrue($payment->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(0, $wallet->availableBalance()->amount());

        $ledgerCount = (int) $this->entityManager->createQuery(
            'SELECT COUNT(e.id) FROM '.LedgerEntry::class.' e'
        )->getSingleScalarResult();
        $this->assertSame(0, $ledgerCount);
    }

    public function testInitiateIsIdempotentForSameRequest(): void
    {
        $command = $this->command();

        ($this->handler)($command);
        ($this->handler)($command);

        $count = (int) $this->entityManager->createQuery(
            'SELECT COUNT(p.id) FROM '.Payment::class.' p'
        )->getSingleScalarResult();
        $this->assertSame(1, $count);
        $this->assertSame(0, $this->wallets->getById(WalletId::fromString(self::WALLET_ID))->availableBalance()->amount());
    }

    public function testInitiateConflictsWhenIdempotencyKeyReusedWithDifferentAmount(): void
    {
        ($this->handler)($this->command(amountMinor: 2500));

        $this->expectException(PaymentIdempotencyConflictException::class);

        ($this->handler)($this->command(amountMinor: 1000));
    }

    public function testRejectsWalletBelongingToAnotherPlayer(): void
    {
        $this->expectException(InvalidPaymentException::class);

        ($this->handler)($this->command(walletId: self::OTHER_WALLET_ID));
    }

    public function testRejectsCurrencyMismatch(): void
    {
        $this->expectException(InvalidPaymentException::class);

        ($this->handler)($this->command(currency: 'EUR'));
    }

    public function testRejectsInactivePlayer(): void
    {
        $player = $this->players->getById(PlayerId::fromString(self::PLAYER_ID));
        $player->block();
        $this->players->save($player);

        $this->expectException(InvalidPaymentException::class);

        ($this->handler)($this->command());
    }

    public function testDeclinedProviderFailsPaymentWithoutCreditingWallet(): void
    {
        $declining = new class implements PaymentProviderInterface {
            public function supports(string $provider): bool
            {
                return 'fake' === strtolower(trim($provider));
            }

            public function initiate(PaymentProviderRequest $request): PaymentProviderResult
            {
                return new PaymentProviderResult(
                    PaymentProviderInitiationStatus::Declined,
                    null,
                    'card_declined',
                );
            }
        };

        $handler = new InitiateDepositHandler(
            $this->payments,
            $this->players,
            $this->wallets,
            new PaymentProviderResolver([$declining]),
        );

        ($handler)($this->command(paymentId: '01GXYZ00000000000000000005', idempotencyKey: 'dep-declined-001'));

        $payment = $this->payments->getById(PaymentId::fromString('01GXYZ00000000000000000005'));
        $this->assertSame(PaymentStatus::Failed, $payment->status());
        $this->assertSame('card_declined', $payment->failureReason());
        $this->assertSame(0, $this->wallets->getById(WalletId::fromString(self::WALLET_ID))->availableBalance()->amount());
    }

    private function command(
        string $paymentId = self::PAYMENT_ID,
        string $walletId = self::WALLET_ID,
        int $amountMinor = 2500,
        string $currency = 'USD',
        string $idempotencyKey = self::IDEMPOTENCY_KEY,
    ): InitiateDepositCommand {
        return new InitiateDepositCommand(
            $paymentId,
            self::PLAYER_ID,
            $walletId,
            $amountMinor,
            $currency,
            $idempotencyKey,
        );
    }
}
