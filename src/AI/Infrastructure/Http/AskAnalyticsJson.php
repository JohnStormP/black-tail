<?php

namespace App\AI\Infrastructure\Http;

use App\AI\Application\Ask\AskAnalyticsAnswer;

final class AskAnalyticsJson
{
    /**
     * @return array{
     *     answer: string,
     *     intent: array{metric: string, group_by: string, period: string}
     * }
     */
    public static function fromAnswer(AskAnalyticsAnswer $answer): array
    {
        return [
            'answer' => $answer->text,
            'intent' => [
                'metric' => $answer->intent->metric->value,
                'group_by' => $answer->intent->groupBy->value,
                'period' => $answer->intent->period->value,
            ],
        ];
    }
}
