<?php

namespace App\Game\Infrastructure\EventListener;

use App\Analytics\Application\Message\RecordBetPlacedAnalyticsMessage;
use App\Analytics\Application\Message\RecordBetSettledAnalyticsMessage;
use App\Game\Domain\Event\LuckyReelsSpun;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class DispatchLuckyReelsAnalyticsMessageListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(LuckyReelsSpun $event): void
    {
        $occurredAt = $event->occurredAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
        $roundId = $event->roundId()->toString();
        $playerId = $event->playerId()->toString();
        $tenantId = $event->tenantId()->toString();
        $stake = $event->stake();

        $this->bus->dispatch(new RecordBetPlacedAnalyticsMessage(
            $roundId,
            $playerId,
            $event->gameId()->toString(),
            $roundId,
            $stake->amount(),
            $stake->currency()->value,
            $occurredAt,
            $tenantId,
        ));

        $this->bus->dispatch(new RecordBetSettledAnalyticsMessage(
            $roundId,
            $playerId,
            $event->outcome(),
            $stake->amount(),
            $event->payoutMinor(),
            $stake->currency()->value,
            $occurredAt,
            $tenantId,
        ));
    }
}
