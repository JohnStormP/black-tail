<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Repository\GameRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListGamesController
{
    public function __construct(
        private readonly GameRepositoryInterface $games,
    ) {
    }

    #[Route('/api/games', name: 'api_games_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        $items = $this->games->findAll();

        return new JsonResponse(array_map(GameJson::from(...), $items), Response::HTTP_OK);
    }
}
