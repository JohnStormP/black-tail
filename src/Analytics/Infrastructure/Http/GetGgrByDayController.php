<?php

namespace App\Analytics\Infrastructure\Http;

use App\Analytics\Application\Handler\GetGgrByDayHandler;
use App\Analytics\Application\Query\GetGgrByDayQuery;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetGgrByDayController
{
    public function __construct(
        private readonly GetGgrByDayHandler $getGgrByDay,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/analytics/ggr-by-day', name: 'api_analytics_ggr_by_day', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapQueryString] GgrByDayRequest $request): Response
    {
        try {
            $tenantId = TenantId::fromString($request->tenantId);
        } catch (InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        try {
            $stats = ($this->getGgrByDay)(new GetGgrByDayQuery($request->from, $request->to, $request->tenantId));
        } catch (InvalidAnalyticsPeriodException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(GgrByDayJson::fromStats($request->from, $request->to, $stats));
    }
}
