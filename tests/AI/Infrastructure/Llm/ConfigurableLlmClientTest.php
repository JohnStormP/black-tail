<?php

namespace App\Tests\AI\Infrastructure\Llm;

use App\AI\Infrastructure\Llm\ConfigurableLlmClient;
use App\AI\Infrastructure\Llm\FakeLlmClient;
use App\AI\Infrastructure\Llm\HttpOpenAiCompatibleLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfigurableLlmClientTest extends TestCase
{
    public function testUsesFakeWhenBaseUrlOrApiKeyEmpty(): void
    {
        $fakeCompletion = '{"metric":"BETS","group_by":"DAY","period":"TODAY"}';
        $fake = new FakeLlmClient($fakeCompletion);
        $http = new HttpOpenAiCompatibleLlmClient(
            new MockHttpClient(function (): MockResponse {
                $this->fail('HTTP LLM must not be called');
            }),
            'https://example.invalid/v1',
            'unused-key',
            'gpt-4o-mini',
        );

        $withoutBaseUrl = new ConfigurableLlmClient($fake, $http, '', 'some-key');
        $this->assertSame(
            $fakeCompletion,
            $withoutBaseUrl->complete('Return JSON only.', 'Bets today'),
        );

        $withoutApiKey = new ConfigurableLlmClient($fake, $http, 'https://example.invalid/v1', '');
        $this->assertSame(
            $fakeCompletion,
            $withoutApiKey->complete('Return JSON only.', 'Bets today'),
        );
    }

    public function testUsesHttpWhenBaseUrlAndApiKeySet(): void
    {
        $httpCompletion = '{"metric":"GGR","group_by":"GAME","period":"LAST_WEEK"}';
        $mock = new MockHttpClient(static function (string $method, string $url) use ($httpCompletion): MockResponse {
            TestCase::assertSame('POST', $method);
            TestCase::assertStringContainsString('/chat/completions', $url);

            return new MockResponse(json_encode([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $httpCompletion,
                        ],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR));
        });

        $client = new ConfigurableLlmClient(
            new FakeLlmClient('{"metric":"BETS","group_by":"DAY","period":"TODAY"}'),
            new HttpOpenAiCompatibleLlmClient(
                $mock,
                'https://generativelanguage.googleapis.com/v1beta/openai',
                'gemini-test-key',
                'gemini-2.0-flash',
            ),
            'https://generativelanguage.googleapis.com/v1beta/openai',
            'gemini-test-key',
        );

        $this->assertSame(
            $httpCompletion,
            $client->complete('Return JSON only.', 'GGR last week by game'),
        );
    }
}
