<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Application\Handler\RegisterGameHandler;
use App\Game\Domain\Exception\GameAlreadyExistsException;
use App\Game\Domain\Exception\InvalidGameCodeException;
use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\Exception\InvalidGameNameException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RegisterGameController
{
    public function __construct(
        private readonly RegisterGameHandler $registerGame,
        private readonly GameRepositoryInterface $games,
    ) {
    }

    #[Route('/api/games', name: 'api_games_register', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function __invoke(#[MapRequestPayload] RegisterGameRequest $body): Response
    {
        try {
            $gameId = GameId::fromString($body->gameId);
        } catch (InvalidGameIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $this->games->findById($gameId)) {
            return new JsonResponse(['error' => 'Game already exists'], Response::HTTP_CONFLICT);
        }

        try {
            ($this->registerGame)(new RegisterGameCommand(
                $gameId->toString(),
                $body->code,
                $body->name,
            ));
        } catch (InvalidGameIdException|InvalidGameCodeException|InvalidGameNameException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (GameAlreadyExistsException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $game = $this->games->getById($gameId);

        return new JsonResponse(GameJson::from($game), Response::HTTP_CREATED);
    }
}
