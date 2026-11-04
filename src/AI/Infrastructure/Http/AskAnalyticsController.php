<?php

namespace App\AI\Infrastructure\Http;

use App\AI\Application\Ask\AskAnalyticsQuestionQuery;
use App\AI\Application\Handler\AskAnalyticsQuestionHandler;
use App\AI\Domain\Exception\InvalidAnalyticsIntentException;
use App\AI\Domain\Exception\LlmException;
use App\AI\Domain\Exception\UnsupportedAnalyticsIntentException;
use App\AI\Infrastructure\Security\AiAskAccessVoter;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AskAnalyticsController
{
    public function __construct(
        private readonly AskAnalyticsQuestionHandler $ask,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/ai/ask', name: 'api_ai_ask', methods: ['POST'])]
    #[IsGranted(AiAskAccessVoter::AI_ASK)]
    #[RateLimit(limiter: 'ai_ask', key: new Expression('args["user"].userId()'))]
    public function __invoke(
        #[MapRequestPayload] AskAnalyticsRequest $body,
        #[CurrentUser] SecurityUser $user,
    ): Response {
        $question = $body->question;
        if ('' === $question) {
            return new JsonResponse(['error' => 'Question must not be blank'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $tenantId = TenantId::fromString($body->tenantId);
        } catch (InvalidTenantIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $tenantId)) {
            throw new AccessDeniedHttpException();
        }

        try {
            $answer = ($this->ask)(new AskAnalyticsQuestionQuery($question, $tenantId->toString()));
        } catch (UnsupportedAnalyticsIntentException|InvalidAnalyticsIntentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (LlmException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(AskAnalyticsJson::fromAnswer($answer), Response::HTTP_OK);
    }
}
