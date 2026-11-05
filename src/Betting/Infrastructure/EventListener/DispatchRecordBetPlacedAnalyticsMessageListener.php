<?php

namespace App\Betting\Infrastructure\EventListener;

use App\Analytics\Application\Message\RecordBetPlacedAnalyticsMessage;
use App\Betting\Domain\Event\BetPlaced;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class DispatchRecordBetPlacedAnalyticsMessageListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(BetPlaced $event): void
    {
        $message = new RecordBetPlacedAnalyticsMessage(
            $event->betId()->toString(),
            $event->playerId()->toString(),
            $event->gameId()->toString(),
            $event->roundId()->toString(),
            $event->amount()->amount(),
            $event->amount()->currency()->value,
            $event->occurredAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            $event->tenantId()->toString(),
        );
        $this->bus->dispatch($message);
    }
}
