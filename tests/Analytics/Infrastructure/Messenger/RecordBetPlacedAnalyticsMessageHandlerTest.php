<?php

namespace App\Tests\Analytics\Infrastructure\Messenger;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use App\Analytics\Application\Message\RecordBetPlacedAnalyticsMessage;
use App\Analytics\Infrastructure\Messenger\RecordBetPlacedAnalyticsMessageHandler;
use App\Tests\Shared\Doubles\InMemoryConsumedMessageStore;
use PHPUnit\Framework\TestCase;

final class RecordBetPlacedAnalyticsMessageHandlerTest extends TestCase
{
    public function testInsertsIntoClickHouseOnce(): void
    {
        $clickHouse = new ClickHouseExecuteSpy();
        $handler = new RecordBetPlacedAnalyticsMessageHandler($clickHouse, new InMemoryConsumedMessageStore());
        $message = $this->message();

        $handler($message);
        $handler($message);

        $this->assertCount(1, $clickHouse->executed);
        $this->assertStringContainsString('INSERT INTO bet_events', $clickHouse->executed[0]);
        $this->assertStringContainsString('FORMAT JSONEachRow', $clickHouse->executed[0]);
        $this->assertStringContainsString('01ARZ3NDEKTSV4RRFFQ69G5FAV', $clickHouse->executed[0]);
    }

    public function testSkipsWhenAlreadyClaimed(): void
    {
        $clickHouse = new ClickHouseExecuteSpy();
        $store = new InMemoryConsumedMessageStore();
        $store->claim(RecordBetPlacedAnalyticsMessageHandler::CONSUMER, '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $handler = new RecordBetPlacedAnalyticsMessageHandler($clickHouse, $store);

        $handler($this->message());

        $this->assertSame([], $clickHouse->executed);
    }

    private function message(): RecordBetPlacedAnalyticsMessage
    {
        return new RecordBetPlacedAnalyticsMessage(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            '01CXYZ00000000000000000001',
            '01DXYZ00000000000000000002',
            100,
            'USD',
            '2026-10-20T12:00:00+00:00',
        );
    }
}

final class ClickHouseExecuteSpy implements ClickHouseClientInterface
{
    /** @var list<string> */
    public array $executed = [];

    public function ping(): void
    {
    }

    public function execute(string $sql): void
    {
        $this->executed[] = $sql;
    }

    public function select(string $sql): array
    {
        return [];
    }
}
