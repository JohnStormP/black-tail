<?php

namespace App\Tests\Payment\Application\Handler;

use App\Payment\Application\Command\FailStalePaymentCommand;
use App\Payment\Application\Command\InitiateDepositCommand;
use App\Payment\Application\Command\InitiateWithdrawalCommand;
use App\Payment\Application\Handler\FailStalePaymentHandler;
use App\Payment\Application\Handler\InitiateDepositHandler;
use App\Payment\Application\Handler\InitiateWithdrawalHandler;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Provider\PaymentProviderResolver;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
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
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FailStalePaymentHandlerTest extends KernelTestCase
{
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string WALLET_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string DEPOSIT_PAYMENT_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string WITHDRAWAL_PAYMENT_ID = '01EXYZ00000000000000000003';
    private const string SEED_DEPOSIT_ENTRY_ID = '01FXYZ00000000000000000004';
    private const string RESERVE_ENTRY_ID = '01GXYZ00000000000000000005';
    private const string RELEASE_ENTRY_ID = '01HXYZ00000000000000000006';

    private FailStalePaymentHandler $failStale;
    private InitiateDepositHandler $initiateDeposit;
    private InitiateWithdrawalHandler $initiateWithdrawal;
    private PaymentRepositoryInterface $payments;
    private WalletRepositoryInterface $wallets;
    private LedgerEntryRepositoryInterface $ledger;

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

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $entityManager->createQuery('DELETE FROM '.Payment::class)->execute();
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(TenantId::fromString(self::TENANT_ID), 'Casino Alpha'));
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));

        $open = new OpenWalletHandler($this->wallets);
        ($open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));

        $deposit = new DepositWalletHandler($this->wallets, $this->ledger, $transactions);
        ($deposit)(new DepositWalletCommand(self::WALLET_ID, self::SEED_DEPOSIT_ENTRY_ID, 5000));

        $resolver = new PaymentProviderResolver([new FakePaymentProvider()]);
        $this->initiateDeposit = new InitiateDepositHandler(
            $this->payments,
            $players,
            $this->wallets,
            $resolver,
        );
        $this->initiateWithdrawal = new InitiateWithdrawalHandler(
            $this->payments,
            $players,
            $this->wallets,
            $this->ledger,
            $resolver,
            $transactions,
        );
        $this->failStale = new FailStalePaymentHandler(
            $this->payments,
            $this->wallets,
            $this->ledger,
            $transactions,
        );
    }

    public function testFailsStaleProcessingWithdrawalAndReleasesReserve(): void
    {
        ($this->initiateWithdrawal)(new InitiateWithdrawalCommand(
            self::WITHDRAWAL_PAYMENT_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            2000,
            'USD',
            'wd-stale-001',
            self::RESERVE_ENTRY_ID,
            self::RELEASE_ENTRY_ID,
        ));

        ($this->failStale)(new FailStalePaymentCommand(
            self::WITHDRAWAL_PAYMENT_ID,
            (new \DateTimeImmutable('+1 minute'))->format(\DateTimeInterface::ATOM),
            self::RELEASE_ENTRY_ID,
        ));

        $payment = $this->payments->getById(PaymentId::fromString(self::WITHDRAWAL_PAYMENT_ID));
        $this->assertSame(PaymentStatus::Failed, $payment->status());
        $this->assertSame('stale_timeout', $payment->failureReason());

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(5000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());

        $release = $this->ledger->getById(LedgerEntryId::fromString(self::RELEASE_ENTRY_ID));
        $this->assertSame(LedgerEntryType::Release, $release->type());
    }

    public function testFailStaleIsIdempotentWhenAlreadyFailed(): void
    {
        ($this->initiateWithdrawal)(new InitiateWithdrawalCommand(
            self::WITHDRAWAL_PAYMENT_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            2000,
            'USD',
            'wd-stale-002',
            self::RESERVE_ENTRY_ID,
            self::RELEASE_ENTRY_ID,
        ));

        $command = new FailStalePaymentCommand(
            self::WITHDRAWAL_PAYMENT_ID,
            (new \DateTimeImmutable('+1 minute'))->format(\DateTimeInterface::ATOM),
            self::RELEASE_ENTRY_ID,
        );

        ($this->failStale)($command);
        ($this->failStale)($command);

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(5000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->reservedBalance()->amount());
    }

    public function testCancelsStalePendingDeposit(): void
    {
        $this->payments->save(Payment::initiate(
            PaymentId::fromString(self::DEPOSIT_PAYMENT_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            PaymentType::Deposit,
            Money::of(1000, Currency::Usd),
            PaymentIdempotencyKey::fromString('dep-pending-stale-001'),
            'fake',
        ));

        ($this->failStale)(new FailStalePaymentCommand(
            self::DEPOSIT_PAYMENT_ID,
            (new \DateTimeImmutable('+1 minute'))->format(\DateTimeInterface::ATOM),
        ));

        $payment = $this->payments->getById(PaymentId::fromString(self::DEPOSIT_PAYMENT_ID));
        $this->assertSame(PaymentStatus::Cancelled, $payment->status());
        $this->assertSame(5000, $this->wallets->getById(WalletId::fromString(self::WALLET_ID))->availableBalance()->amount());
    }

    public function testRejectsWhenPaymentIsNotYetStale(): void
    {
        ($this->initiateDeposit)(new InitiateDepositCommand(
            self::DEPOSIT_PAYMENT_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            1000,
            'USD',
            'dep-stale-early-001',
        ));

        $this->expectException(InvalidPaymentException::class);

        ($this->failStale)(new FailStalePaymentCommand(
            self::DEPOSIT_PAYMENT_ID,
            (new \DateTimeImmutable('-1 hour'))->format(\DateTimeInterface::ATOM),
        ));
    }

    public function testProcessingWithdrawalRequiresReleaseEntryId(): void
    {
        ($this->initiateWithdrawal)(new InitiateWithdrawalCommand(
            self::WITHDRAWAL_PAYMENT_ID,
            self::PLAYER_ID,
            self::WALLET_ID,
            2000,
            'USD',
            'wd-stale-003',
            self::RESERVE_ENTRY_ID,
            self::RELEASE_ENTRY_ID,
        ));

        $this->expectException(InvalidPaymentException::class);

        ($this->failStale)(new FailStalePaymentCommand(
            self::WITHDRAWAL_PAYMENT_ID,
            (new \DateTimeImmutable('+1 minute'))->format(\DateTimeInterface::ATOM),
            null,
        ));
    }
}
