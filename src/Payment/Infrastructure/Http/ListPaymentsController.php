<?php

namespace App\Payment\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListPaymentsController
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/payments', name: 'api_payments_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): Response
    {
        $rawTenantId = $request->query->get('tenantId');
        if (empty($rawTenantId)) {
            throw new BadRequestHttpException('tenantId query is required');
        }

        try {
            $tenantId = TenantId::fromString($rawTenantId);
        } catch (InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        $type = null;
        $rawType = $request->query->get('type');
        if (!empty($rawType)) {
            $type = PaymentType::tryFrom($rawType);
            if (null === $type) {
                return new JsonResponse(['error' => 'Invalid payment type'], Response::HTTP_BAD_REQUEST);
            }
        }

        $items = $this->payments->findByTenantId($tenantId, $type);

        return new JsonResponse(array_map(PaymentJson::from(...), $items), Response::HTTP_OK);
    }
}
