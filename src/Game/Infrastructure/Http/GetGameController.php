<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Exception\GameNotFoundException;
use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Infrastructure\Cache\GameCatalogCache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetGameController
{
    public function __construct(
        private readonly GameCatalogCache $gameCatalog,
    ) {
    }

    #[Route('/api/games/{gameId}', name: 'api_games_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $gameId): Response
    {
        try {
            $id = GameId::fromString($gameId);
            $payload = $this->gameCatalog->get($id);
        } catch (InvalidGameIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (GameNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($payload, Response::HTTP_OK);
    }
}
