<?php

namespace App\AI\Infrastructure\Http;

use App\AI\Application\Ask\AskAnalyticsQuestionQuery;
use App\AI\Application\Handler\AskAnalyticsQuestionHandler;
use App\AI\Domain\Exception\InvalidAnalyticsIntentException;
use App\AI\Domain\Exception\LlmException;
use App\AI\Domain\Exception\UnsupportedAnalyticsIntentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AskAnalyticsController
{
    public function __construct(
        private readonly AskAnalyticsQuestionHandler $ask,
    ) {
    }

    #[Route('/api/ai/ask', name: 'api_ai_ask', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] AskAnalyticsRequest $body): Response
    {
        $question = $body->question;
        if ('' === $question) {
            return new JsonResponse(['error' => 'Question must not be blank'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $answer = ($this->ask)(new AskAnalyticsQuestionQuery($question));
        } catch (UnsupportedAnalyticsIntentException|InvalidAnalyticsIntentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (LlmException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(AskAnalyticsJson::fromAnswer($answer), Response::HTTP_OK);
    }
}
