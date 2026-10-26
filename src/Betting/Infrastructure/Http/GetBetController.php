<?php

namespace App\Betting\Infrastructure\Http;

use App\Betting\Application\Handler\GetBetHandler;
use App\Betting\Application\Query\GetBetQuery;
use App\Betting\Domain\Exception\BetNotFoundException;
use App\Betting\Domain\Exception\InvalidBetIdException;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetBetController
{
    public function __construct(
        private readonly GetBetHandler $getBet,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/bets/{betId}', name: 'api_bets_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $betId): Response
    {
        try {
            $bet = ($this->getBet)(new GetBetQuery($betId));
        } catch (BetNotFoundException|InvalidBetIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        $player = $this->players->getById($bet->playerId());

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        return new JsonResponse(BetJson::from($bet), Response::HTTP_OK);
    }
}
