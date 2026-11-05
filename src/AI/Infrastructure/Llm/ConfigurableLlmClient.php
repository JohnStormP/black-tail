<?php

namespace App\AI\Infrastructure\Llm;

use App\AI\Application\Llm\LlmClientInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(LlmClientInterface::class, public: true)]
final class ConfigurableLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly FakeLlmClient $fake,
        private readonly HttpOpenAiCompatibleLlmClient $http,
        #[Autowire('%env(AI_LLM_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(AI_LLM_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function complete(string $systemPrompt, string $userMessage): string
    {
        if ('' !== trim($this->baseUrl) && '' !== trim($this->apiKey)) {
            return $this->http->complete($systemPrompt, $userMessage);
        }

        return $this->fake->complete($systemPrompt, $userMessage);
    }
}
