<?php

namespace App\Payment\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Payment\Domain\Exception\InvalidPaymentIdException;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetPaymentController
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/payments/{paymentId}', name: 'api_payments_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $paymentId): Response
    {
        try {
            $id = PaymentId::fromString($paymentId);
            $payment = $this->payments->getById($id);
        } catch (InvalidPaymentIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PaymentNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $payment->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        return new JsonResponse(PaymentJson::from($payment), Response::HTTP_OK);
    }
}
