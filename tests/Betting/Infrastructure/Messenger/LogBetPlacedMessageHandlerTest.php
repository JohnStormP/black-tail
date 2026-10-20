<?php

namespace App\Tests\Betting\Infrastructure\Messenger;

use App\Betting\Application\Message\LogBetPlacedMessage;
use App\Betting\Infrastructure\Messenger\LogBetPlacedMessageHandler;
use App\Tests\Shared\Doubles\InMemoryConsumedMessageStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class LogBetPlacedMessageHandlerTest extends TestCase
{
    public function testLogsBetPlacedMessage(): void
    {
        $logger = $this->logger();
        $handler = new LogBetPlacedMessageHandler($logger, new InMemoryConsumedMessageStore());
        $handler($this->message());

        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Bet placed', $logger->records[0]['message']);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $logger->records[0]['context']['bet_id']);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $logger->records[0]['context']['player_id']);
        $this->assertSame(100, $logger->records[0]['context']['amount']);
        $this->assertSame('USD', $logger->records[0]['context']['currency']);
    }

    public function testDoesNotLogTwiceForSameBetId(): void
    {
        $logger = $this->logger();
        $handler = new LogBetPlacedMessageHandler($logger, new InMemoryConsumedMessageStore());
        $message = $this->message();
        $handler($message);
        $handler($message);
        $this->assertCount(1, $logger->records);
    }

    private function message(): LogBetPlacedMessage
    {
        return new LogBetPlacedMessage(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            100,
            'USD',
        );
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: string, message: string, context: array<string, mixed>}>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
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
    }
}
