<?php

namespace App\AI\Infrastructure\Llm;

use App\AI\Application\Llm\LlmClientInterface;
use App\AI\Domain\Exception\LlmException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpOpenAiCompatibleLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(AI_LLM_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(AI_LLM_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(AI_LLM_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function complete(string $systemPrompt, string $userMessage): string
    {
        if ('' === trim($this->baseUrl) || '' === trim($this->apiKey)) {
            throw LlmException::requestFailed('LLM is not configured');
        }
        $baseUrl = rtrim($this->baseUrl, '/');
        $url = "$baseUrl/chat/completions";
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0,
                ],
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw LlmException::requestFailed($e->getMessage());
        }

        if (200 !== $statusCode) {
            throw LlmException::requestFailed('LLM response code: '.$statusCode);
        }

        try {
            $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw LlmException::invalidResponse('JSON error: '.$e->getMessage());
        }

        if (!is_array($data)) {
            throw LlmException::invalidResponse('expected JSON object');
        }

        $choices = $data['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            throw LlmException::invalidResponse('expected choices[0] object');
        }

        $message = $choices[0]['message'] ?? null;
        if (!is_array($message) || !array_key_exists('content', $message) || !is_string($message['content'])) {
            throw LlmException::invalidResponse('expected choices[0].message.content string');
        }

        $content = trim($message['content']);
        if ('' === $content) {
            throw LlmException::emptyCompletion();
        }

        return $content;
    }
}
