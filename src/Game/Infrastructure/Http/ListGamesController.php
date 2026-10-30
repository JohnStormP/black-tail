<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Infrastructure\Cache\GameCatalogCache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListGamesController
{
    public function __construct(
        private readonly GameCatalogCache $gameCatalog,
    ) {
    }

    #[Route('/api/games', name: 'api_games_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        return new JsonResponse($this->gameCatalog->list(), Response::HTTP_OK);
    }
}
