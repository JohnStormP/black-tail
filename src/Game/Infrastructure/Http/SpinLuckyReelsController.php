<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\SpinLuckyReelsCommand;
use App\Game\Application\Handler\SpinLuckyReelsHandler;
use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Exception\GameNotFoundException;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Game\Domain\Exception\GameProviderNotFoundException;
use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\Exception\InvalidGameRoundException;
use App\Game\Domain\Exception\InvalidGameRoundIdException;
use App\Game\Domain\Repository\GameRoundRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SpinLuckyReelsController
{
    public function __construct(
        private readonly SpinLuckyReelsHandler $spinLuckyReels,
        private readonly GameRoundRepositoryInterface $rounds,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/games/lucky-reels/spin', name: 'api_games_lucky_reels_spin', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] SpinLuckyReelsRequest $body): Response
    {
        try {
            $playerId = PlayerId::fromString($body->playerId);
            $gameRoundId = GameRoundId::fromString($body->roundId);
            $gameId = GameId::fromString($body->gameId);
            $player = $this->players->getById($playerId);
        } catch (InvalidPlayerIdException|InvalidGameRoundIdException|InvalidGameIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        $existing = $this->rounds->findById($gameRoundId);
        if (null !== $existing) {
            if ($this->matchesRequest($existing, $gameId, $playerId, $body->betMinor)) {
                return new JsonResponse(GameRoundJson::from($existing), Response::HTTP_OK);
            }

            return new JsonResponse(['error' => 'Game round already exists'], Response::HTTP_CONFLICT);
        }

        try {
            ($this->spinLuckyReels)(new SpinLuckyReelsCommand(
                $gameRoundId->toString(),
                $gameId->toString(),
                $playerId->toString(),
                $body->betMinor,
            ));
        } catch (GameNotPlayableException|InvalidGameRoundException|GameProviderNotFoundException|InvalidGameIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (GameNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->rounds->findById($gameRoundId);
            if (null === $existing) {
                return new JsonResponse(['error' => 'Game round cannot be found'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            if ($this->matchesRequest($existing, $gameId, $playerId, $body->betMinor)) {
                return new JsonResponse(GameRoundJson::from($existing), Response::HTTP_OK);
            }

            return new JsonResponse(['error' => 'Game round already exists'], Response::HTTP_CONFLICT);
        }

        $round = $this->rounds->getById($gameRoundId);

        return new JsonResponse(GameRoundJson::from($round), Response::HTTP_CREATED);
    }

    private function matchesRequest(
        GameRound $existing,
        GameId $gameId,
        PlayerId $playerId,
        int $betMinor,
    ): bool {
        return $existing->gameId()->equals($gameId)
            && $existing->playerId()->equals($playerId)
            && $existing->betMinor() === $betMinor;
    }
}
