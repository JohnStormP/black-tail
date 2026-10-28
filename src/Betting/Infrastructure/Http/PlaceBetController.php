<?php

namespace App\Betting\Infrastructure\Http;

use App\Betting\Application\Command\PlaceBetCommand;
use App\Betting\Application\Handler\PlaceBetHandler;
use App\Betting\Domain\Exception\BetIdempotencyConflictException;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PlaceBetController
{
    public function __construct(
        private readonly PlaceBetHandler $placeBet,
        private readonly BetRepositoryInterface $bets,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/bets', name: 'api_bets_place', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] PlaceBetRequest $body): Response
    {
        try {
            $player = $this->players->getById(PlayerId::fromString($body->playerId));

            if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
                throw new AccessDeniedHttpException();
            }
            ($this->placeBet)(new PlaceBetCommand(
                $body->betId,
                $body->playerId,
                $body->gameId,
                $body->roundId,
                $body->amountMinor,
                $body->currency,
                $body->idempotencyKey,
                $body->providerReference
            ));
            $bet = $this->bets->getById(BetId::fromString($body->betId));
        } catch (BetIdempotencyConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidBetException|GameNotPlayableException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(BetJson::from($bet), Response::HTTP_CREATED);
    }
}
