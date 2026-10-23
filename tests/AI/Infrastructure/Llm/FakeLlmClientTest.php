<?php

namespace App\Tests\AI\Infrastructure\Llm;

use App\AI\Infrastructure\Llm\FakeLlmClient;
use PHPUnit\Framework\TestCase;

final class FakeLlmClientTest extends TestCase
{
    public function testCompleteReturnsFixedJson(): void
    {
        $client = new FakeLlmClient('{"metric":"GGR","group_by":"GAME","period":"TODAY"}');

        $text = $client->complete(
            'Return JSON intent only.',
            'Show GGR by game today',
        );

        $this->assertSame('{"metric":"GGR","group_by":"GAME","period":"TODAY"}', $text);
    }

    public function testRespondWithChangesNextCompletion(): void
    {
        $client = new FakeLlmClient();
        $client->respondWith('{"metric":"DEPOSITS","group_by":"COUNTRY","period":"LAST_MONTH"}');

        $text = $client->complete('system', 'user question');

        $this->assertSame('{"metric":"DEPOSITS","group_by":"COUNTRY","period":"LAST_MONTH"}', $text);
    }
}
