<?php

namespace App\Payment\Application\Handler;

use App\Payment\Application\Command\InitiateDepositCommand;
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
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class InitiateDepositHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PlayerRepositoryInterface $players,
        private readonly WalletRepositoryInterface $wallets,
        private readonly PaymentProviderResolver $providers,
    ) {
    }

    public function __invoke(InitiateDepositCommand $command): void
    {
        $paymentId = PaymentId::fromString($command->paymentId);
        $playerId = PlayerId::fromString($command->playerId);
        $walletId = WalletId::fromString($command->walletId);
        $money = Money::of($command->amountMinor, Currency::from($command->currency));
        $paymentIdempotencyKey = PaymentIdempotencyKey::fromString($command->idempotencyKey);
        $player = $this->players->getById($playerId);

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

        $payment = Payment::initiate($paymentId, $player->tenantId(), $playerId, $walletId, PaymentType::Deposit, $money, $paymentIdempotencyKey, $command->provider);

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

        $result = $this->providers
            ->resolve($payment->provider())
            ->initiate(new PaymentProviderRequest(
                $paymentId,
                PaymentType::Deposit,
                $money,
                $paymentIdempotencyKey,
            ));

        if ($result->isDeclined()) {
            $payment->markProcessing();
            $payment->fail($result->declineReason ?? 'declined');
            $this->payments->save($payment);

            return;
        }

        if ($result->isSubmitted()) {
            $payment->markProcessing($result->providerReference);
            $this->payments->save($payment);
        }
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
            && PaymentType::Deposit === $existing->type()
            && $existing->amount()->equals($amount)
            && $existing->provider() === trim($provider);
    }
}
