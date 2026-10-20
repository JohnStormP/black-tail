<?php

namespace App\Tests\Risk\Infrastructure\Messenger;

use App\Risk\Application\Message\LogRiskSignalRaisedMessage;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Infrastructure\Messenger\LogRiskSignalRaisedMessageHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class LogRiskSignalRaisedMessageHandlerTest extends TestCase
{
    public function testLogsRiskSignalRaisedMessage(): void
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

        $handler = new LogRiskSignalRaisedMessageHandler($logger);
        $handler(new LogRiskSignalRaisedMessage(
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            '01CXYZ00000000000000000001',
            RiskOperation::Bet->value,
            RiskRuleAction::Flag->value,
            ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
        ));

        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame('Risk signal raised', $logger->records[0]['message']);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $logger->records[0]['context']['tenant_id']);
        $this->assertSame('01CXYZ00000000000000000001', $logger->records[0]['context']['player_id']);
        $this->assertSame(RiskOperation::Bet->value, $logger->records[0]['context']['operation']);
        $this->assertSame(RiskRuleAction::Flag->value, $logger->records[0]['context']['action']);
        $this->assertSame(['01ARZ3NDEKTSV4RRFFQ69G5FAV'], $logger->records[0]['context']['matched_rule_ids']);
    }
}
