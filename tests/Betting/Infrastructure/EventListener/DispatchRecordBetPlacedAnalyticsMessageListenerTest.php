<?php

namespace App\Tests\Betting\Infrastructure\EventListener;

use App\Analytics\Application\Message\RecordBetPlacedAnalyticsMessage;
use App\Betting\Domain\Event\BetPlaced;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Infrastructure\EventListener\DispatchRecordBetPlacedAnalyticsMessageListener;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DispatchRecordBetPlacedAnalyticsMessageListenerTest extends TestCase
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

        $listener = new DispatchRecordBetPlacedAnalyticsMessageListener($bus);
        $listener(new BetPlaced(
            BetId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            GameId::fromString('01CXYZ00000000000000000001'),
            GameRoundId::fromString('01DXYZ00000000000000000002'),
            Money::of(100, Currency::Usd),
        ));
        $this->assertInstanceOf(RecordBetPlacedAnalyticsMessage::class, $bus->dispatched);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $bus->dispatched->betId);
        $this->assertSame('01BX5ZZKBKACTAV9WEVGEMMVRY', $bus->dispatched->playerId);
        $this->assertSame('01CXYZ00000000000000000001', $bus->dispatched->gameId);
        $this->assertSame('01DXYZ00000000000000000002', $bus->dispatched->roundId);
        $this->assertSame(100, $bus->dispatched->amountMinor);
        $this->assertSame('USD', $bus->dispatched->currency);
    }
}
