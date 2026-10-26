<?php

namespace App\AI\Application\Ask;

use App\Analytics\Application\Query\BetsByDayStat;

final class BetsByDayAnswerFormatter
{
    /**
     * @param list<BetsByDayStat> $stats
     *
     * @return non-empty-string
     */
    public function format(string $fromDate, string $toDate, array $stats): string
    {
        if ([] === $stats) {
            return sprintf('No bets found from %s to %s (UTC).', $fromDate, $toDate);
        }

        $lines = [
            sprintf('Bets by day from %s to %s (UTC):', $fromDate, $toDate),
        ];
        foreach ($stats as $stat) {
            $lines[] = sprintf(
                '- %s: %d bets, amount_minor=%d',
                $stat->day,
                $stat->betsCount,
                $stat->amountMinor,
            );
        }

        return implode("\n", $lines);
    }
}
