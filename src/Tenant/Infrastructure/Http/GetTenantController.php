<?php

namespace App\Tenant\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetTenantController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    #[Route('/api/tenants/{tenantId}', name: 'api_tenants_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[IsGranted(TenantAccessVoter::TENANT_ACCESS, 'tenantId')]
    public function __invoke(string $tenantId): Response
    {
        $id = TenantId::fromString($tenantId);
        $tenant = $this->tenants->getById($id);

        return new JsonResponse([
            'id' => $tenant->id()->toString(),
            'name' => $tenant->name(),
            'status' => $tenant->status()->value,
        ], Response::HTTP_OK);
    }
}
