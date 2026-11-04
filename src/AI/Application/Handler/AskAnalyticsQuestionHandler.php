<?php

namespace App\AI\Application\Handler;

use App\AI\Application\Ask\AnalystSystemPrompt;
use App\AI\Application\Ask\AnalyticsIntentAllowlist;
use App\AI\Application\Ask\AnalyticsPeriodDateRange;
use App\AI\Application\Ask\AskAnalyticsAnswer;
use App\AI\Application\Ask\AskAnalyticsQuestionQuery;
use App\AI\Application\Ask\BetsByDayAnswerFormatter;
use App\AI\Application\Llm\AnalyticsIntentParser;
use App\AI\Application\Llm\LlmClientInterface;
use App\Analytics\Application\Handler\GetBetsByDayHandler;
use App\Analytics\Application\Query\GetBetsByDayQuery;
use Psr\Clock\ClockInterface;

final class AskAnalyticsQuestionHandler
{
    public function __construct(
        private readonly LlmClientInterface $llm,
        private readonly AnalyticsIntentParser $intentParser,
        private readonly AnalyticsIntentAllowlist $allowlist,
        private readonly AnalyticsPeriodDateRange $periodDates,
        private readonly GetBetsByDayHandler $getBetsByDay,
        private readonly BetsByDayAnswerFormatter $answerFormatter,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(AskAnalyticsQuestionQuery $query): AskAnalyticsAnswer
    {
        $llmJson = $this->llm->complete(AnalystSystemPrompt::TEXT, $query->question);
        $intent = $this->intentParser->parse($llmJson);
        $this->allowlist->assertSupported($intent);
        $periodDates = $this->periodDates->toInclusiveYmd($intent->period, $this->clock->now());
        $from = $periodDates['from'];
        $to = $periodDates['to'];
        $stats = ($this->getBetsByDay)(new GetBetsByDayQuery($from, $to, $query->tenantId));
        $text = $this->answerFormatter->format($from, $to, $stats);

        return new AskAnalyticsAnswer($text, $intent);
    }
}
