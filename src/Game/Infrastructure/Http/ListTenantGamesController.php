<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListTenantGamesController
{
    public function __construct(
        private readonly TenantGameRepositoryInterface $tenantGames,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/tenant-games', name: 'api_tenant_games_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): Response
    {
        $raw = $request->query->get('tenantId');
        if (empty($raw)) {
            throw new BadRequestHttpException('tenantId query is required');
        }

        try {
            $tenantId = TenantId::fromString($raw);
        } catch (InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        $items = $this->tenantGames->findByTenantId($tenantId);

        return new JsonResponse(array_map(TenantGameJson::from(...), $items), Response::HTTP_OK);
    }
}
