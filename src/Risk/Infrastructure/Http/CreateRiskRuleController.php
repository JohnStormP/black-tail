<?php

namespace App\Risk\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Risk\Application\Command\CreateRiskRuleCommand;
use App\Risk\Application\Handler\CreateRiskRuleHandler;
use App\Risk\Domain\Repository\RiskRuleRepositoryInterface;
use App\Risk\Domain\ValueObject\RiskRuleId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CreateRiskRuleController
{
    public function __construct(
        private readonly CreateRiskRuleHandler $create,
        private readonly RiskRuleRepositoryInterface $rules,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/risk-rules', name: 'api_risk_rules_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] CreateRiskRuleRequest $body): Response
    {
        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $body->tenantId)) {
            throw new AccessDeniedHttpException();
        }

        $command = new CreateRiskRuleCommand(
            $body->ruleId,
            $body->tenantId,
            $body->name,
            $body->type,
            $body->threshold,
            $body->action,
            $body->windowSeconds
        );

        ($this->create)($command);
        $rule = $this->rules->getById(RiskRuleId::fromString($body->ruleId));

        return new JsonResponse(RiskRuleJson::from($rule), Response::HTTP_CREATED);
    }
}
