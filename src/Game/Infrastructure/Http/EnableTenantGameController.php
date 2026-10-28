<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\EnableTenantGameCommand;
use App\Game\Application\Handler\EnableTenantGameHandler;
use App\Game\Domain\Exception\InvalidTenantGameIdException;
use App\Game\Domain\Exception\TenantGameNotFoundException;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EnableTenantGameController
{
    public function __construct(
        private readonly EnableTenantGameHandler $enableTenantGame,
        private readonly TenantGameRepositoryInterface $tenantGames,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/tenant-games/{tenantGameId}/enable', name: 'api_tenant_games_enable', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $tenantGameId): Response
    {
        try {
            $id = TenantGameId::fromString($tenantGameId);
            $link = $this->tenantGames->getById($id);
        } catch (InvalidTenantGameIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (TenantGameNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $link->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        ($this->enableTenantGame)(new EnableTenantGameCommand($id->toString()));
        $link = $this->tenantGames->getById($id);

        return new JsonResponse(TenantGameJson::from($link), Response::HTTP_OK);
    }
}
