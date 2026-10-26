<?php

namespace App\Tests\AI\Application\Ask;

use App\AI\Application\Ask\BetsByDayAnswerFormatter;
use App\Analytics\Application\Query\BetsByDayStat;
use PHPUnit\Framework\TestCase;

final class BetsByDayAnswerFormatterTest extends TestCase
{
    public function testFormatsEmptyStats(): void
    {
        $text = new BetsByDayAnswerFormatter()->format('2026-10-15', '2026-10-21', []);

        $this->assertSame('No bets found from 2026-10-15 to 2026-10-21 (UTC).', $text);
    }

    public function testFormatsDayLines(): void
    {
        $text = new BetsByDayAnswerFormatter()->format('2026-10-20', '2026-10-21', [
            new BetsByDayStat('2026-10-20', 2, 150),
            new BetsByDayStat('2026-10-21', 1, 50),
        ]);

        $this->assertStringContainsString('Bets by day from 2026-10-20 to 2026-10-21 (UTC):', $text);
        $this->assertStringContainsString('- 2026-10-20: 2 bets, amount_minor=150', $text);
        $this->assertStringContainsString('- 2026-10-21: 1 bets, amount_minor=50', $text);
    }
}
