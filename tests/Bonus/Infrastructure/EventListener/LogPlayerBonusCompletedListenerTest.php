<?php

namespace App\Tests\Bonus\Infrastructure\EventListener;

use App\Bonus\Domain\Event\PlayerBonusCompleted;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Infrastructure\EventListener\LogPlayerBonusCompletedListener;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class LogPlayerBonusCompletedListenerTest extends TestCase
{
    public function testLogsPlayerBonusCompleted(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $listener = new LogPlayerBonusCompletedListener($logger);
        $listener(new PlayerBonusCompleted(
            PlayerBonusId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            BonusCampaignId::fromString('01CXYZ00000000000000000001'),
            Money::of(1000, Currency::Usd),
        ));

        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Player bonus completed', $logger->records[0]['message']);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $logger->records[0]['context']['player_bonus_id']);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $logger->records[0]['context']['player_id']);
        $this->assertSame('01CXYZ00000000000000000001', $logger->records[0]['context']['campaign_id']);
        $this->assertSame(1000, $logger->records[0]['context']['granted_amount']);
        $this->assertSame('USD', $logger->records[0]['context']['currency']);
    }
}
