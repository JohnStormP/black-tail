<?php

namespace App\AI\Application\Llm;

use App\AI\Domain\Exception\InvalidAnalyticsIntentException;
use App\AI\Domain\ValueObject\AnalyticsIntent;

final class AnalyticsIntentParser
{
    public function parse(string $llmJson): AnalyticsIntent
    {
        try {
            $data = json_decode($llmJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw InvalidAnalyticsIntentException::invalidJson($e->getMessage());
        }

        if (!is_array($data)) {
            throw InvalidAnalyticsIntentException::invalidJson($llmJson);
        }

        return AnalyticsIntent::fromArray($data);
    }
}
