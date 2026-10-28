<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\AssignGameToTenantCommand;
use App\Game\Application\Handler\AssignGameToTenantHandler;
use App\Game\Domain\Exception\GameNotFoundException;
use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\Exception\InvalidTenantGameIdException;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AssignGameToTenantController
{
    public function __construct(
        private readonly AssignGameToTenantHandler $assignGameToTenant,
        private readonly TenantGameRepositoryInterface $tenantGames,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/tenant-games', name: 'api_tenant_games_assign', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] AssignGameToTenantRequest $body): Response
    {
        try {
            $tenantGameId = TenantGameId::fromString($body->tenantGameId);
            $tenantId = TenantId::fromString($body->tenantId);
            $gameId = GameId::fromString($body->gameId);
        } catch (InvalidTenantGameIdException|InvalidTenantIdException|InvalidGameIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        $existing = $this->tenantGames->findByTenantAndGame($tenantId, $gameId);

        try {
            ($this->assignGameToTenant)(new AssignGameToTenantCommand(
                $tenantGameId->toString(),
                $tenantId->toString(),
                $gameId->toString(),
            ));
        } catch (GameNotFoundException|TenantNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        $link = $this->tenantGames->findByTenantAndGame($tenantId, $gameId);
        if (null === $link) {
            return new JsonResponse(['error' => 'Tenant game link cannot be found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(
            TenantGameJson::from($link),
            null === $existing ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
