<?php

namespace App\Tests\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\Query\GgrByDayStat;
use App\Analytics\Infrastructure\ClickHouse\ClickHouseBetsAnalyticsQuery;
use PHPUnit\Framework\TestCase;

final class ClickHouseGgrByDayQueryTest extends TestCase
{
    public function testMapsSelectRowsToGgrStats(): void
    {
        $client = new ClickHouseSelectSpy([
            ['day' => '2026-10-20', 'stake_minor' => 300, 'payout_minor' => 100, 'ggr_minor' => 200],
        ]);
        $query = new ClickHouseBetsAnalyticsQuery($client);
        $from = new \DateTimeImmutable('2026-10-20 00:00:00');
        $to = new \DateTimeImmutable('2026-10-22 00:00:00');

        $stats = $query->ggrByDay($from, $to, '01EXYZ00000000000000000099');

        $this->assertEquals([
            new GgrByDayStat('2026-10-20', 300, 100, 200),
        ], $stats);

        $this->assertNotNull($client->lastSql);
        $this->assertStringContainsString('bet_settled_events', $client->lastSql);
        $this->assertStringContainsString('sum(stake_minor)', $client->lastSql);
        $this->assertStringContainsString('ggr_minor', $client->lastSql);
        $this->assertStringContainsString('GROUP BY', $client->lastSql);
        $this->assertStringContainsString('ORDER BY', $client->lastSql);
        $this->assertStringContainsString('2026-10-20 00:00:00', $client->lastSql);
        $this->assertStringContainsString('2026-10-22 00:00:00', $client->lastSql);
        $this->assertStringContainsString('01EXYZ00000000000000000099', $client->lastSql);
    }
}
