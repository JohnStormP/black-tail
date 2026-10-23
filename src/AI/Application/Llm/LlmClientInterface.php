<?php

namespace App\AI\Application\Llm;

interface LlmClientInterface
{
    /**
     * @param non-empty-string $systemPrompt
     * @param non-empty-string $userMessage
     *
     * @return non-empty-string
     */
    public function complete(string $systemPrompt, string $userMessage): string;
}
