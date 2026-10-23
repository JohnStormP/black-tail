<?php

namespace App\AI\Infrastructure\Llm;

use App\AI\Application\Llm\LlmClientInterface;
use App\AI\Domain\Exception\LlmException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Deterministic LLM for tests.
 */
#[AsAlias(LlmClientInterface::class, public: true)]
final class FakeLlmClient implements LlmClientInterface
{
    /**
     * @param non-empty-string $fixedCompletion
     */
    public function __construct(
        private string $fixedCompletion = '{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}',
    ) {
    }

    /**
     * @param non-empty-string $fixedCompletion
     */
    public function respondWith(string $fixedCompletion): void
    {
        $this->fixedCompletion = $fixedCompletion;
    }

    public function complete(string $systemPrompt, string $userMessage): string
    {
        if ('' === trim($systemPrompt) || '' === trim($userMessage)) {
            throw LlmException::requestFailed('system prompt and user message must be non-empty');
        }

        $completion = trim($this->fixedCompletion);
        if ('' === $completion) {
            throw LlmException::emptyCompletion();
        }

        return $completion;
    }
}
