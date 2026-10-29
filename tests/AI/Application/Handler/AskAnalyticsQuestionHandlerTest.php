<?php

namespace App\Tests\AI\Application\Handler;

use App\AI\Application\Ask\AnalyticsIntentAllowlist;
use App\AI\Application\Ask\AnalyticsPeriodDateRange;
use App\AI\Application\Ask\AskAnalyticsQuestionQuery;
use App\AI\Application\Ask\BetsByDayAnswerFormatter;
use App\AI\Application\Handler\AskAnalyticsQuestionHandler;
use App\AI\Application\Llm\AnalyticsIntentParser;
use App\AI\Domain\Exception\UnsupportedAnalyticsIntentException;
use App\AI\Domain\ValueObject\AnalyticsGroupBy;
use App\AI\Domain\ValueObject\AnalyticsMetric;
use App\AI\Domain\ValueObject\AnalyticsPeriod;
use App\AI\Infrastructure\Llm\FakeLlmClient;
use App\Analytics\Application\ClickHouse\BetsAnalyticsQueryInterface;
use App\Analytics\Application\Handler\GetBetsByDayHandler;
use App\Analytics\Application\Query\BetsByDayStat;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AskAnalyticsQuestionHandlerTest extends TestCase
{
    public function testAnswersBetsByDayFromLlmIntent(): void
    {
        $llm = new FakeLlmClient('{"metric":"BETS","group_by":"DAY","period":"LAST_WEEK"}');
        $betsAnalytics = new class implements BetsAnalyticsQueryInterface {
            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                return [new BetsByDayStat('2026-10-20', 2, 150)];
            }

            public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                return [];
            }
        };

        $handler = new AskAnalyticsQuestionHandler(
            $llm,
            new AnalyticsIntentParser(),
            new AnalyticsIntentAllowlist(),
            new AnalyticsPeriodDateRange(),
            new GetBetsByDayHandler($betsAnalytics),
            new BetsByDayAnswerFormatter(),
            new MockClock('2026-10-21 12:00:00', 'UTC'),
        );

        $answer = $handler(new AskAnalyticsQuestionQuery('How many bets last week?'));

        $this->assertSame(AnalyticsMetric::Bets, $answer->intent->metric);
        $this->assertSame(AnalyticsGroupBy::Day, $answer->intent->groupBy);
        $this->assertSame(AnalyticsPeriod::LastWeek, $answer->intent->period);
        $this->assertStringContainsString('2026-10-20', $answer->text);
        $this->assertStringContainsString('2 bets', $answer->text);
    }

    public function testRejectsUnsupportedIntentFromLlm(): void
    {
        $llm = new FakeLlmClient('{"metric":"GGR","group_by":"GAME","period":"TODAY"}');
        $betsAnalytics = new class implements BetsAnalyticsQueryInterface {
            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                Assert::fail('ClickHouse must not be called');
            }

            public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                return [];
            }
        };

        $handler = new AskAnalyticsQuestionHandler(
            $llm,
            new AnalyticsIntentParser(),
            new AnalyticsIntentAllowlist(),
            new AnalyticsPeriodDateRange(),
            new GetBetsByDayHandler($betsAnalytics),
            new BetsByDayAnswerFormatter(),
            new MockClock('2026-10-21 12:00:00', 'UTC'),
        );

        $this->expectException(UnsupportedAnalyticsIntentException::class);
        $handler(new AskAnalyticsQuestionQuery('Show GGR by game'));
    }

    public function testSqlLikeQuestionStillUsesPeriodDatesOnly(): void
    {
        $llm = new FakeLlmClient('{"metric":"BETS","group_by":"DAY","period":"TODAY"}');
        $betsAnalytics = new class implements BetsAnalyticsQueryInterface {
            public ?string $seenFrom = null;
            public ?string $seenToExclusive = null;

            public function betsByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                $this->seenFrom = $fromInclusive->format('Y-m-d');
                $this->seenToExclusive = $toExclusive->format('Y-m-d');

                return [new BetsByDayStat('2026-10-21', 1, 10)];
            }

            public function ggrByDay(\DateTimeImmutable $fromInclusive, \DateTimeImmutable $toExclusive): array
            {
                return [];
            }
        };

        $handler = new AskAnalyticsQuestionHandler(
            $llm,
            new AnalyticsIntentParser(),
            new AnalyticsIntentAllowlist(),
            new AnalyticsPeriodDateRange(),
            new GetBetsByDayHandler($betsAnalytics),
            new BetsByDayAnswerFormatter(),
            new MockClock('2026-10-21 12:00:00', 'UTC'),
        );

        $answer = $handler(new AskAnalyticsQuestionQuery('; DROP TABLE bets;--'));

        $this->assertSame('2026-10-21', $betsAnalytics->seenFrom);
        $this->assertSame('2026-10-22', $betsAnalytics->seenToExclusive);
        $this->assertStringContainsString('2026-10-21', $answer->text);
        $this->assertStringNotContainsString('DROP TABLE', $answer->text);
    }
}
