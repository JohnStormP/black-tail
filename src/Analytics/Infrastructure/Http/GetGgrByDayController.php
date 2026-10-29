<?php

namespace App\Analytics\Infrastructure\Http;

use App\Analytics\Application\Handler\GetGgrByDayHandler;
use App\Analytics\Application\Query\GetGgrByDayQuery;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetGgrByDayController
{
    public function __construct(
        private readonly GetGgrByDayHandler $getGgrByDay,
    ) {
    }

    #[Route('/api/analytics/ggr-by-day', name: 'api_analytics_ggr_by_day', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapQueryString] GgrByDayRequest $request): Response
    {
        try {
            $stats = ($this->getGgrByDay)(new GetGgrByDayQuery($request->from, $request->to));
        } catch (InvalidAnalyticsPeriodException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(GgrByDayJson::fromStats($request->from, $request->to, $stats));
    }
}
