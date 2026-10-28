<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\DisableTenantGameCommand;
use App\Game\Application\Handler\DisableTenantGameHandler;
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

final class DisableTenantGameController
{
    public function __construct(
        private readonly DisableTenantGameHandler $disableTenantGame,
        private readonly TenantGameRepositoryInterface $tenantGames,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/tenant-games/{tenantGameId}/disable', name: 'api_tenant_games_disable', methods: ['POST'])]
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

        ($this->disableTenantGame)(new DisableTenantGameCommand($id->toString()));
        $link = $this->tenantGames->getById($id);

        return new JsonResponse(TenantGameJson::from($link), Response::HTTP_OK);
    }
}
