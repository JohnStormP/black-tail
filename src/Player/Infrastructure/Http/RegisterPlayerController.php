<?php

namespace App\Player\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Application\Handler\RegisterPlayerHandler;
use App\Player\Domain\Exception\InvalidPlayerEmailException;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RegisterPlayerController
{
    public function __construct(
        private readonly RegisterPlayerHandler $registerPlayer,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/players', name: 'api_players_register', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] RegisterPlayerRequest $body): Response
    {
        try {
            $playerId = PlayerId::fromString($body->playerId);
            $tenantId = TenantId::fromString($body->tenantId);
        } catch (InvalidPlayerIdException|InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        $player = $this->players->findById($playerId);

        if (null !== $player) {
            return new JsonResponse(['error' => 'Player already exists'], Response::HTTP_CONFLICT);
        }
        try {
            ($this->registerPlayer)(new RegisterPlayerCommand(
                $body->playerId,
                $body->tenantId,
                $body->email
            ));
        } catch (InvalidPlayerEmailException|InvalidPlayerIdException|InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['error' => 'Player already exists'], Response::HTTP_CONFLICT);
        }
        $player = $this->players->getById($playerId);

        return new JsonResponse(PlayerJson::from($player), Response::HTTP_CREATED);
    }
}
