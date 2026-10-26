<?php

namespace App\Payment\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Payment\Application\Command\InitiateDepositCommand;
use App\Payment\Application\Handler\InitiateDepositHandler;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\PaymentIdempotencyConflictException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InitiateDepositController
{
    public function __construct(
        private readonly InitiateDepositHandler $initiateDeposit,
        private readonly PaymentRepositoryInterface $payments,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/payments/deposits', name: 'api_payments_deposits_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] InitiateDepositRequest $body): Response
    {
        try {
            $player = $this->players->getById(PlayerId::fromString($body->playerId));

            if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
                throw new AccessDeniedHttpException();
            }

            ($this->initiateDeposit)(new InitiateDepositCommand(
                $body->paymentId,
                $body->playerId,
                $body->walletId,
                $body->amountMinor,
                $body->currency,
                $body->idempotencyKey,
                $body->provider,
            ));
            $payment = $this->payments->getById(PaymentId::fromString($body->paymentId));
        } catch (PaymentIdempotencyConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (InvalidPaymentException|PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(PaymentJson::from($payment), Response::HTTP_CREATED);
    }
}
