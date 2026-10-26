<?php

namespace App\AI\Application\Ask;

final class AnalystSystemPrompt
{
    public const string TEXT = <<<'PROMPT'
You are BLACK-TAIL analytics intent extractor.
Reply with a single JSON object only, no markdown, no prose.
Schema:
{"metric":"GGR|BETS|DEPOSITS","group_by":"DAY|GAME|COUNTRY","period":"TODAY|LAST_WEEK|LAST_MONTH"}
Prefer BETS + DAY when the user asks about bets over time.
PROMPT;
}
