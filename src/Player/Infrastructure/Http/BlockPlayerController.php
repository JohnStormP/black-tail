<?php

namespace App\Player\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Application\Command\BlockPlayerCommand;
use App\Player\Application\Handler\BlockPlayerHandler;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BlockPlayerController
{
    public function __construct(
        private readonly BlockPlayerHandler $blockPlayer,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/players/{playerId}/block', name: 'api_players_block', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $playerId): Response
    {
        try {
            $player = $this->players->getById(PlayerId::fromString($playerId));
        } catch (InvalidPlayerIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        ($this->blockPlayer)(new BlockPlayerCommand($playerId));

        $player = $this->players->getById(PlayerId::fromString($playerId));

        return new JsonResponse(PlayerJson::from($player), Response::HTTP_OK);
    }
}
