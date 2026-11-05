<?php

namespace App\Tests\Payment\Application\Handler;

use App\Payment\Application\Command\InitiateWithdrawalCommand;
use App\Payment\Application\Handler\InitiateWithdrawalHandler;
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
use App\Risk\Application\Service\RiskGuard;
use App\Shared\Application\TransactionManagerInterface;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\InsufficientFundsException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InitiateWithdrawalHandlerTest extends KernelTestCase
{
    private const string PAYMENT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string WALLET_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string OTHER_WALLET_ID = '01EXYZ00000000000000000003';
    private const string OTHER_PLAYER_ID = '01FXYZ00000000000000000004';
    private const string IDEMPOTENCY_KEY = 'wd-req-001';
    private const string RESERVE_ENTRY_ID = '01GXYZ00000000000000000005';
    private const string RELEASE_ENTRY_ID = '01HXYZ00000000000000000006';
    private const string DEPOSIT_ENTRY_ID = '01JXYZ00000000000000000007';

    private InitiateWithdrawalHandler $handler;
    private PaymentRepositoryInterface $payments;
    private WalletRepositoryInterface $wallets;
    private LedgerEntryRepositoryInterface $ledger;
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

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $this->ledger = $ledger;

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $this->players = $players;

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

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

        $deposit = new DepositWalletHandler($this->wallets, $this->ledger, $transactions);
        ($deposit)(new DepositWalletCommand(self::WALLET_ID, self::DEPOSIT_ENTRY_ID, 5000));

        $risk = self::getContainer()->get(RiskGuard::class);
        self::assertInstanceOf(RiskGuard::class, $risk);

        $this->handler = new InitiateWithdrawalHandler(
            $this->payments,
            $this->players,
            $this->wallets,
            $this->ledger,
            new PaymentProviderResolver([new FakePaymentProvider()]),
            $transactions,
            $risk,
        );
    }

    public function testInitiateReservesFundsAndMarksProcessing(): void
    {
        ($this->handler)($this->command());

        $payment = $this->payments->getById(PaymentId::fromString(self::PAYMENT_ID));
        $this->assertSame(PaymentType::Withdrawal, $payment->type());
        $this->assertSame(PaymentStatus::Processing, $payment->status());
        $this->assertSame(2000, $payment->amount()->amount());
        $this->assertSame('fake-'.self::PAYMENT_ID, $payment->providerReference());

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(3000, $wallet->availableBalance()->amount());
        $this->assertSame(2000, $wallet->reservedBalance()->amount());

        $reserve = $this->ledger->getById(LedgerEntryId::fromString(self::RESERVE_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Reserve, $reserve->type());
        $this->assertSame(2000, $reserve->amount()->amount());
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

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(3000, $wallet->availableBalance()->amount());
        $this->assertSame(2000, $wallet->reservedBalance()->amount());
    }

    public function testInitiateConflictsWhenIdempotencyKeyReusedWithDifferentAmount(): void
    {
        ($this->handler)($this->command(amountMinor: 2000));

        $this->expectException(PaymentIdempotencyConflictException::class);

        ($this->handler)($this->command(
            paymentId: '01KXYZ00000000000000000008',
            amountMinor: 1000,
            reserveEntryId: '01RXYZ00000000000000000009',
            releaseEntryId: '01MXYZ0000000000000000000A',
        ));
    }

    public function testRejectsInsufficientFunds(): void
    {
        $this->expectException(InsufficientFundsException::class);

        ($this->handler)($this->command(amountMinor: 9000));
    }

    public function testRejectsWalletBelongingToAnotherPlayer(): void
    {
        $this->expectException(InvalidPaymentException::class);

        ($this->handler)($this->command(walletId: self::OTHER_WALLET_ID));
    }

    public function testDeclinedProviderFailsAndReleasesReservedFunds(): void
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
                    'psp_rejected',
                );
            }
        };

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $risk = self::getContainer()->get(RiskGuard::class);
        self::assertInstanceOf(RiskGuard::class, $risk);

        $handler = new InitiateWithdrawalHandler(
            $this->payments,
            $this->players,
            $this->wallets,
            $this->ledger,
            new PaymentProviderResolver([$declining]),
            $transactions,
            $risk,
        );

        ($handler)($this->command(
            paymentId: '01NXYZ0000000000000000000B',
            idempotencyKey: 'wd-declined-001',
            reserveEntryId: '01PXYZ0000000000000000000C',
            releaseEntryId: '01QXYZ0000000000000000000D',
        ));

        $payment = $this->payments->getById(PaymentId::fromString('01NXYZ0000000000000000000B'));
        $this->assertSame(PaymentStatus::Failed, $payment->status());
        $this->assertSame('psp_rejected', $payment->failureReason());

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(5000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());

        $release = $this->ledger->getById(LedgerEntryId::fromString('01QXYZ0000000000000000000D'));
        $this->assertSame(LedgerEntryType::Release, $release->type());
    }

    public function testFailedRetryReleasesReservedFundsIfReleaseWasMissed(): void
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
                    'psp_rejected',
                );
            }
        };

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $risk = self::getContainer()->get(RiskGuard::class);
        self::assertInstanceOf(RiskGuard::class, $risk);

        $handler = new InitiateWithdrawalHandler(
            $this->payments,
            $this->players,
            $this->wallets,
            $this->ledger,
            new PaymentProviderResolver([$declining]),
            $transactions,
            $risk,
        );

        $command = $this->command(
            paymentId: '01WXYZ0000000000000000000J',
            idempotencyKey: 'wd-failed-retry-001',
            reserveEntryId: '01XXYZ0000000000000000000K',
            releaseEntryId: '01YXYZ0000000000000000000M',
        );

        ($handler)($command);

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $wallet->reserve(Money::of(2000, Currency::Usd));
        $this->wallets->save($wallet);
        $this->entityManager->createQuery(
            'DELETE FROM '.LedgerEntry::class.' e WHERE e.id = :id'
        )->setParameter('id', '01YXYZ0000000000000000000M')->execute();
        $this->entityManager->clear();

        ($handler)($command);

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(5000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());
        $release = $this->ledger->getById(LedgerEntryId::fromString('01YXYZ0000000000000000000M'));
        $this->assertSame(LedgerEntryType::Release, $release->type());
    }

    public function testProviderInitiateRunsOutsideDatabaseTransaction(): void
    {
        $entityManager = $this->entityManager;

        $outsideTx = new class($entityManager) implements PaymentProviderInterface {
            public function __construct(
                private readonly EntityManagerInterface $entityManager,
            ) {
            }

            public function supports(string $provider): bool
            {
                return 'fake' === strtolower(trim($provider));
            }

            public function initiate(PaymentProviderRequest $request): PaymentProviderResult
            {
                InitiateWithdrawalHandlerTest::assertFalse(
                    $this->entityManager->getConnection()->isTransactionActive(),
                );

                return new PaymentProviderResult(
                    PaymentProviderInitiationStatus::Submitted,
                    'outside-tx-'.$request->paymentId->toString(),
                );
            }
        };

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $risk = self::getContainer()->get(RiskGuard::class);
        self::assertInstanceOf(RiskGuard::class, $risk);

        $handler = new InitiateWithdrawalHandler(
            $this->payments,
            $this->players,
            $this->wallets,
            $this->ledger,
            new PaymentProviderResolver([$outsideTx]),
            $transactions,
            $risk,
        );

        ($handler)($this->command(
            paymentId: '01SXYZ0000000000000000000E',
            idempotencyKey: 'wd-outside-tx-001',
            reserveEntryId: '01TXYZ0000000000000000000F',
            releaseEntryId: '01VXYZ0000000000000000000H',
        ));

        $payment = $this->payments->getById(PaymentId::fromString('01SXYZ0000000000000000000E'));
        $this->assertSame(PaymentStatus::Processing, $payment->status());
        $this->assertSame('outside-tx-01SXYZ0000000000000000000E', $payment->providerReference());

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(3000, $wallet->availableBalance()->amount());
        $this->assertSame(2000, $wallet->reservedBalance()->amount());
    }

    private function command(
        string $paymentId = self::PAYMENT_ID,
        string $walletId = self::WALLET_ID,
        int $amountMinor = 2000,
        string $idempotencyKey = self::IDEMPOTENCY_KEY,
        string $reserveEntryId = self::RESERVE_ENTRY_ID,
        string $releaseEntryId = self::RELEASE_ENTRY_ID,
    ): InitiateWithdrawalCommand {
        return new InitiateWithdrawalCommand(
            $paymentId,
            self::PLAYER_ID,
            $walletId,
            $amountMinor,
            'USD',
            $idempotencyKey,
            $reserveEntryId,
            $releaseEntryId,
        );
    }
}
