<?php

namespace App\Tests\Risk\Infrastructure\EventListener;

use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Application\Message\LogRiskSignalRaisedMessage;
use App\Risk\Domain\Event\RiskSignalRaised;
use App\Risk\Domain\ValueObject\RiskOperation;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleId;
use App\Risk\Infrastructure\EventListener\DispatchLogRiskSignalRaisedMessageListener;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DispatchLogRiskSignalRaisedMessageListenerTest extends TestCase
{
    public function testDispatchesLogRiskSignalRaisedMessage(): void
    {
        $bus = new class implements MessageBusInterface {
            public mixed $dispatched = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched = $message;

                return new Envelope($message);
            }
        };

        $ruleId = RiskRuleId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $listener = new DispatchLogRiskSignalRaisedMessageListener($bus);
        $listener(new RiskSignalRaised(
            TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            PlayerId::fromString('01CXYZ00000000000000000001'),
            RiskOperation::Bet,
            RiskRuleAction::Flag,
            [$ruleId],
        ));

        $this->assertInstanceOf(LogRiskSignalRaisedMessage::class, $bus->dispatched);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $bus->dispatched->tenantId);
        $this->assertSame('01CXYZ00000000000000000001', $bus->dispatched->playerId);
        $this->assertSame(RiskOperation::Bet->value, $bus->dispatched->operation);
        $this->assertSame(RiskRuleAction::Flag->value, $bus->dispatched->action);
        $this->assertSame(['01ARZ3NDEKTSV4RRFFQ69G5FAV'], $bus->dispatched->matchedRuleIds);
    }
}
