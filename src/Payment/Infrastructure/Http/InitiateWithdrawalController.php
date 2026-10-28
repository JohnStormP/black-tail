<?php

namespace App\Payment\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Payment\Application\Command\InitiateWithdrawalCommand;
use App\Payment\Application\Handler\InitiateWithdrawalHandler;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\InvalidPaymentIdException;
use App\Payment\Domain\Exception\PaymentIdempotencyConflictException;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\Exception\InvalidWalletIdException;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\ValueObject\WalletId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InitiateWithdrawalController
{
    public function __construct(
        private readonly InitiateWithdrawalHandler $initiateWithdrawal,
        private readonly PaymentRepositoryInterface $payments,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/payments/withdrawals', name: 'api_payments_withdrawals_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] InitiateWithdrawalRequest $body): Response
    {
        try {
            $paymentId = PaymentId::fromString($body->paymentId);
            $playerId = PlayerId::fromString($body->playerId);
            $walletId = WalletId::fromString($body->walletId);
            $player = $this->players->getById($playerId);
        } catch (InvalidPaymentIdException|InvalidPlayerIdException|InvalidWalletIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        $existing = $this->payments->findById($paymentId);
        try {
            ($this->initiateWithdrawal)(new InitiateWithdrawalCommand(
                $paymentId->toString(),
                $playerId->toString(),
                $walletId->toString(),
                $body->amountMinor,
                $body->currency,
                $body->idempotencyKey,
                $body->reserveEntryId,
                $body->releaseEntryId,
                $body->provider
            ));
        } catch (PaymentIdempotencyConflictException|LedgerIdempotencyConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (PlayerNotFoundException|WalletNotFoundException|PaymentNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidPaymentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $payment = $this->payments->getById($paymentId);

        return new JsonResponse(
            PaymentJson::from($payment),
            null === $existing ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
