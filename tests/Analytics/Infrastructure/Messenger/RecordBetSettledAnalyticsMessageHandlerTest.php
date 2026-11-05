<?php

namespace App\Tests\Analytics\Infrastructure\Messenger;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use App\Analytics\Application\Message\RecordBetSettledAnalyticsMessage;
use App\Analytics\Infrastructure\Messenger\RecordBetSettledAnalyticsMessageHandler;
use App\Tests\Shared\Doubles\InMemoryConsumedMessageStore;
use PHPUnit\Framework\TestCase;

final class RecordBetSettledAnalyticsMessageHandlerTest extends TestCase
{
    public function testInsertsIntoClickHouseOnce(): void
    {
        $clickHouse = new ClickHouseExecuteSpy();
        $handler = new RecordBetSettledAnalyticsMessageHandler($clickHouse, new InMemoryConsumedMessageStore());
        $message = $this->message();

        $handler($message);
        $handler($message);

        $this->assertCount(1, $clickHouse->executed);
        $this->assertStringContainsString('INSERT INTO bet_settled_events', $clickHouse->executed[0]);
        $this->assertStringContainsString('FORMAT JSONEachRow', $clickHouse->executed[0]);
        $this->assertStringContainsString('01ARZ3NDEKTSV4RRFFQ69G5FAV', $clickHouse->executed[0]);
        $this->assertStringContainsString('"outcome":"won"', $clickHouse->executed[0]);
        $this->assertStringContainsString('"stake_minor":100', $clickHouse->executed[0]);
        $this->assertStringContainsString('"payout_minor":250', $clickHouse->executed[0]);
        $this->assertStringContainsString('tenant_id', $clickHouse->executed[0]);
        $this->assertStringContainsString('01EXYZ00000000000000000099', $clickHouse->executed[0]);
    }

    public function testSkipsWhenAlreadyClaimed(): void
    {
        $clickHouse = new ClickHouseExecuteSpy();
        $store = new InMemoryConsumedMessageStore();
        $store->claim(RecordBetSettledAnalyticsMessageHandler::CONSUMER, '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $handler = new RecordBetSettledAnalyticsMessageHandler($clickHouse, $store);

        $handler($this->message());

        $this->assertSame([], $clickHouse->executed);
    }

    public function testDoesNotClaimWhenClickHouseInsertFails(): void
    {
        $clickHouse = new class implements ClickHouseClientInterface {
            public function ping(): void
            {
            }

            public function execute(string $sql): void
            {
                throw new \RuntimeException('ch down');
            }

            public function select(string $sql): array
            {
                return [];
            }
        };
        $store = new InMemoryConsumedMessageStore();
        $handler = new RecordBetSettledAnalyticsMessageHandler($clickHouse, $store);

        try {
            $handler($this->message());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('ch down', $e->getMessage());
        }

        $this->assertFalse($store->has(
            RecordBetSettledAnalyticsMessageHandler::CONSUMER,
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ));
        $this->assertTrue($store->claim(
            RecordBetSettledAnalyticsMessageHandler::CONSUMER,
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ));
    }

    private function message(): RecordBetSettledAnalyticsMessage
    {
        return new RecordBetSettledAnalyticsMessage(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'won',
            100,
            250,
            'USD',
            '2026-10-20T12:00:00+00:00',
            '01EXYZ00000000000000000099',
        );
    }
}
