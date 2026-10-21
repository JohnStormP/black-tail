<?php

namespace App\Analytics\Infrastructure\Http;

use App\Analytics\Application\Handler\GetBetsByDayHandler;
use App\Analytics\Application\Query\GetBetsByDayQuery;
use App\Analytics\Domain\Exception\InvalidAnalyticsPeriodException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetBetsByDayController
{
    public function __construct(
        private readonly GetBetsByDayHandler $getBetsByDay,
    ) {
    }

    #[Route('/api/analytics/bets-by-day', name: 'api_analytics_bets_by_day', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapQueryString] BetsByDayRequest $request): Response
    {
        try {
            $stats = ($this->getBetsByDay)(new GetBetsByDayQuery($request->from, $request->to));
        } catch (InvalidAnalyticsPeriodException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(BetsByDayJson::fromStats($request->from, $request->to, $stats));
    }
}
