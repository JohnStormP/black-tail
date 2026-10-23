<?php

namespace App\Tests\AI\Application\Llm;

use App\AI\Application\Llm\AnalyticsIntentParser;
use App\AI\Domain\Exception\InvalidAnalyticsIntentException;
use App\AI\Domain\ValueObject\AnalyticsGroupBy;
use App\AI\Domain\ValueObject\AnalyticsMetric;
use App\AI\Domain\ValueObject\AnalyticsPeriod;
use PHPUnit\Framework\TestCase;

final class AnalyticsIntentParserTest extends TestCase
{
    public function testParseValidJson(): void
    {
        $parser = new AnalyticsIntentParser();

        $intent = $parser->parse('{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}');
        $this->assertSame(AnalyticsMetric::Bets, $intent->metric);
        $this->assertSame(AnalyticsGroupBy::Day, $intent->groupBy);
        $this->assertSame(AnalyticsPeriod::LastWeek, $intent->period);
    }

    public function testParseRejectsMalformedJson(): void
    {
        $this->expectException(InvalidAnalyticsIntentException::class);

        $parser = new AnalyticsIntentParser();
        $parser->parse('{not-json');
    }
}
