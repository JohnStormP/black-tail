<?php

namespace App\Tests\Betting\Infrastructure\EventListener;

use App\Betting\Domain\Event\BetPlaced;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Infrastructure\EventListener\LogBetPlacedListener;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class LogBetPlacedListenerTest extends TestCase
{
    public function testLogsBetPlaced(): void
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

        $listener = new LogBetPlacedListener($logger);
        $listener(new BetPlaced(
            BetId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            GameId::fromString('01CXYZ00000000000000000001'),
            GameRoundId::fromString('01DXYZ00000000000000000002'),
            Money::of(100, Currency::Usd),
        ));

        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Bet placed', $logger->records[0]['message']);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $logger->records[0]['context']['bet_id']);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $logger->records[0]['context']['player_id']);
        $this->assertSame(100, $logger->records[0]['context']['amount']);
        $this->assertSame('USD', $logger->records[0]['context']['currency']);
    }
}
