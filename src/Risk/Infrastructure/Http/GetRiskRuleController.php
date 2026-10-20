<?php

namespace App\Risk\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetRiskRuleController
{
    public function __construct(
        private readonly RiskRuleRepositoryInterface $rules,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/risk-rules/{ruleId}', name: 'api_risk_rules_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $ruleId): Response
    {
        $rule = $this->rules->getById(RiskRuleId::fromString($ruleId));

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $rule->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        return new JsonResponse(RiskRuleJson::from($rule), Response::HTTP_OK);
    }
}
