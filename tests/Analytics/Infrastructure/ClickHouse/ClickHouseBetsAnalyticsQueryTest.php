<?php

namespace App\Tests\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\Query\BetsByDayStat;
use App\Analytics\Infrastructure\ClickHouse\ClickHouseBetsAnalyticsQuery;
use PHPUnit\Framework\TestCase;

final class ClickHouseBetsAnalyticsQueryTest extends TestCase
{
    public function testMapsSelectRowsToStats(): void
    {
        $client = new ClickHouseSelectSpy([
            ['day' => '2026-10-20', 'bets_count' => 2, 'amount_minor' => 150],
            ['day' => '2026-10-21', 'bets_count' => 1, 'amount_minor' => 50],
        ]);
        $query = new ClickHouseBetsAnalyticsQuery($client);
        $from = new \DateTimeImmutable('2026-10-20 00:00:00');
        $to = new \DateTimeImmutable('2026-10-22 00:00:00');

        $stats = $query->betsByDay($from, $to, '01EXYZ00000000000000000099');

        $this->assertEquals([
            new BetsByDayStat('2026-10-20', 2, 150),
            new BetsByDayStat('2026-10-21', 1, 50),
        ], $stats);

        $this->assertNotNull($client->lastSql);
        $this->assertStringContainsString('bet_events', $client->lastSql);
        $this->assertStringContainsString('toDate(occurred_at)', $client->lastSql);
        $this->assertStringContainsString('GROUP BY', $client->lastSql);
        $this->assertStringContainsString('ORDER BY', $client->lastSql);
        $this->assertStringContainsString('2026-10-20 00:00:00', $client->lastSql);
        $this->assertStringContainsString('2026-10-22 00:00:00', $client->lastSql);
        $this->assertStringContainsString('01EXYZ00000000000000000099', $client->lastSql);
    }
}
