<?php

namespace App\Tests\Betting\Infrastructure\EventListener;

use App\Analytics\Application\Message\RecordBetSettledAnalyticsMessage;
use App\Betting\Domain\Event\BetSettled;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetSettlementOutcome;
use App\Betting\Infrastructure\EventListener\DispatchRecordBetSettledAnalyticsMessageListener;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DispatchRecordBetSettledAnalyticsMessageListenerTest extends TestCase
{
    public function testDispatchesAnalyticsMessage(): void
    {
        $bus = new class implements MessageBusInterface {
            public mixed $dispatched = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched = $message;

                return new Envelope($message);
            }
        };

        $listener = new DispatchRecordBetSettledAnalyticsMessageListener($bus);
        $listener(new BetSettled(
            BetId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            WalletId::fromString('01CXYZ00000000000000000001'),
            BetSettlementOutcome::Won,
            Money::of(100, Currency::Usd),
            250,
        ));

        $this->assertInstanceOf(RecordBetSettledAnalyticsMessage::class, $bus->dispatched);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $bus->dispatched->betId);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $bus->dispatched->playerId);
        $this->assertSame('won', $bus->dispatched->outcome);
        $this->assertSame(100, $bus->dispatched->stakeMinor);
        $this->assertSame(250, $bus->dispatched->payoutMinor);
        $this->assertSame('USD', $bus->dispatched->currency);
    }
}
