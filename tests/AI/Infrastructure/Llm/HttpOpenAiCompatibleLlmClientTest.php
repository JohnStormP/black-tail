<?php

namespace App\Tests\AI\Infrastructure\Llm;

use App\AI\Infrastructure\Llm\HttpOpenAiCompatibleLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpOpenAiCompatibleLlmClientTest extends TestCase
{
    public function testCompleteReadsMessageContent(): void
    {
        $mock = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            TestCase::assertSame('POST', $method);
            TestCase::assertStringContainsString('/chat/completions', $url);

            return new MockResponse(json_encode([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}',
                        ],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR));
        });

        $client = new HttpOpenAiCompatibleLlmClient(
            $mock,
            'https://api.openai.com/v1',
            'test-key',
            'gpt-4o-mini',
        );

        $text = $client->complete('Return JSON only.', 'Bets last week by day');

        $this->assertSame('{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}', $text);
    }
}
