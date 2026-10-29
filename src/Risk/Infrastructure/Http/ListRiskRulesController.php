<?php

namespace App\Risk\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
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

final class ListRiskRulesController
{
    public function __construct(
        private readonly RiskRuleRepositoryInterface $rules,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/risk-rules', name: 'api_risk_rules_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): Response
    {
        $rawTenantId = $request->query->get('tenantId');
        if (empty($rawTenantId)) {
            throw new BadRequestHttpException('tenantId query is required');
        }

        try {
            $tenantId = TenantId::fromString($rawTenantId);
        } catch (InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        $items = $this->rules->findByTenantId($tenantId);

        return new JsonResponse(array_map(RiskRuleJson::from(...), $items), Response::HTTP_OK);
    }
}
