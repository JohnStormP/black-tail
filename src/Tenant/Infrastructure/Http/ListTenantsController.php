<?php

namespace App\Tenant\Infrastructure\Http;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListTenantsController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/tenants', name: 'api_tenants_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw new AccessDeniedHttpException();
        }
        $roles = $user->getRoles();

        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            $items = $this->tenants->findAll();
        } else {
            $tenantId = $user->tenantId();

            if (null === $tenantId) {
                $items = [];
            } else {
                $tenant = $this->tenants->findById(TenantId::fromString($tenantId));
                $items = $tenant ? [$tenant] : [];
            }
        }

        return new JsonResponse(
            array_map(static fn (Tenant $tenant) => TenantJson::from($tenant), $items),
            Response::HTTP_OK,
        );
    }
}
