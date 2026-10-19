<?php

namespace App\Payment\Application\Handler;

use App\Payment\Application\Command\InitiateWithdrawalCommand;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\PaymentIdempotencyConflictException;
use App\Payment\Domain\Provider\PaymentProviderRequest;
use App\Payment\Domain\Provider\PaymentProviderResolver;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Application\Service\RiskGuard;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class InitiateWithdrawalHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PlayerRepositoryInterface $players,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly PaymentProviderResolver $providers,
        private readonly TransactionManagerInterface $transactions,
        private readonly RiskGuard $risk,
    ) {
    }

    public function __invoke(InitiateWithdrawalCommand $command): void
    {
        $paymentId = PaymentId::fromString($command->paymentId);
        $playerId = PlayerId::fromString($command->playerId);
        $walletId = WalletId::fromString($command->walletId);
        $money = Money::of($command->amountMinor, Currency::from($command->currency));
        $paymentIdempotencyKey = PaymentIdempotencyKey::fromString($command->idempotencyKey);
        $reserveEntryId = LedgerEntryId::fromString($command->reserveEntryId);
        $releaseEntryId = LedgerEntryId::fromString($command->releaseEntryId);
        $player = $this->players->getById($playerId);
        $provider = $command->provider;

        if (!$player->isActive()) {
            throw InvalidPaymentException::playerNotActive($playerId);
        }

        $wallet = $this->wallets->getById($walletId);

        if (!$wallet->playerId()->equals($playerId)) {
            throw InvalidPaymentException::walletPlayerMismatch($paymentId, $walletId);
        }

        if (!$wallet->isActive()) {
            throw InvalidPaymentException::walletNotActive($walletId);
        }

        if ($wallet->currency() !== $money->currency()) {
            throw InvalidPaymentException::walletCurrencyMismatch($paymentId, $walletId);
        }

        $existing = $this->payments->findByIdempotencyKey($paymentIdempotencyKey);

        if (null !== $existing) {
            if ($this->matchesRequest($existing, $paymentId, $playerId, $walletId, $money, $command->provider)) {
                return;
            }
            throw PaymentIdempotencyConflictException::forKey($paymentIdempotencyKey);
        }

        $this->risk->assertAllowed(
            $player->tenantId(),
            $playerId,
            RiskOperation::Withdrawal,
            $money->amount(),
        );

        $this->transactions->transactional(function () use (
            $releaseEntryId,
            $provider,
            $reserveEntryId,
            $paymentId,
            $player,
            $playerId,
            $walletId,
            $money,
            $paymentIdempotencyKey,
            $command
        ): void {
            $payment = Payment::initiate(
                $paymentId,
                $player->tenantId(),
                $playerId,
                $walletId,
                PaymentType::Withdrawal,
                $money,
                $paymentIdempotencyKey,
                $command->provider
            );
            try {
                $this->payments->save($payment);
            } catch (UniqueConstraintViolationException) {
                $existing = $this->payments->findByIdempotencyKey($paymentIdempotencyKey);
                if (null !== $existing && $this->matchesRequest(
                    $existing,
                    $paymentId,
                    $playerId,
                    $walletId,
                    $money,
                    $command->provider,
                )) {
                    return;
                }
                throw PaymentIdempotencyConflictException::forKey($paymentIdempotencyKey);
            }
            $wallet = $this->wallets->getByIdForUpdate($walletId);

            $existingReserve = $this->ledger->findById($reserveEntryId);

            if (null !== $existingReserve) {
                if (
                    !$existingReserve->walletId()->equals($walletId)
                    || LedgerEntryType::Reserve !== $existingReserve->type()
                    || !$existingReserve->amount()->equals($money)
                ) {
                    throw LedgerIdempotencyConflictException::forEntry($reserveEntryId);
                }
            } else {
                $wallet->reserve($money);
                $this->wallets->save($wallet);
                $this->ledger->save(LedgerEntry::record(
                    $reserveEntryId,
                    $wallet->id(),
                    LedgerEntryType::Reserve,
                    $money,
                    $wallet->availableBalance(),
                ));
            }
            $result = $this->providers
                ->resolve($provider)
                ->initiate(new PaymentProviderRequest(
                    $paymentId,
                    PaymentType::Withdrawal,
                    $money,
                    $paymentIdempotencyKey,
                ));
            if ($result->isDeclined()) {
                $payment->markProcessing();
                $payment->fail($result->declineReason ?? 'declined');
                $this->payments->save($payment);
                $existingRelease = $this->ledger->findById($releaseEntryId);
                if (null !== $existingRelease) {
                    if (
                        !$existingRelease->walletId()->equals($walletId)
                        || LedgerEntryType::Release !== $existingRelease->type()
                        || !$existingRelease->amount()->equals($money)
                    ) {
                        throw LedgerIdempotencyConflictException::forEntry($releaseEntryId);
                    }
                } else {
                    $wallet->release($money);
                    $this->wallets->save($wallet);
                    $this->ledger->save(LedgerEntry::record(
                        $releaseEntryId,
                        $wallet->id(),
                        LedgerEntryType::Release,
                        $money,
                        $wallet->availableBalance(),
                    ));
                }

                return;
            }

            if ($result->isSubmitted()) {
                $payment->markProcessing($result->providerReference);
                $this->payments->save($payment);
            }
        });
    }

    private function matchesRequest(
        Payment $existing,
        PaymentId $paymentId,
        PlayerId $playerId,
        WalletId $walletId,
        Money $amount,
        string $provider,
    ): bool {
        return $existing->id()->equals($paymentId)
            && $existing->playerId()->equals($playerId)
            && $existing->walletId()->equals($walletId)
            && PaymentType::Withdrawal === $existing->type()
            && $existing->amount()->equals($amount)
            && $existing->provider() === trim($provider);
    }
}
