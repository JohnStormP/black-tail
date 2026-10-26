<?php

namespace App\Tenant\Infrastructure\Http;

use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\Exception\InvalidTenantNameException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RegisterTenantController
{
    public function __construct(
        private readonly RegisterTenantHandler $registerTenant,
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    #[Route('/api/tenants', name: 'api_tenants_register', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function __invoke(#[MapRequestPayload] RegisterTenantRequest $body): Response
    {
        try {
            $tenantId = TenantId::fromString($body->tenantId);
        } catch (InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $this->tenants->findById($tenantId)) {
            return new JsonResponse(['error' => 'Tenant already exists'], Response::HTTP_CONFLICT);
        }

        try {
            ($this->registerTenant)(new RegisterTenantCommand($body->tenantId, $body->name));
        } catch (InvalidTenantNameException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'Tenant already exists'], Response::HTTP_CONFLICT);
        }

        $tenant = $this->tenants->getById($tenantId);

        return new JsonResponse(TenantJson::from($tenant), Response::HTTP_CREATED);
    }
}
