<?php

namespace App\Betting\Infrastructure\EventListener;

use App\Analytics\Application\Message\RecordBetSettledAnalyticsMessage;
use App\Betting\Domain\Event\BetSettled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class DispatchRecordBetSettledAnalyticsMessageListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(BetSettled $event): void
    {
        $message = new RecordBetSettledAnalyticsMessage(
            $event->betId()->toString(),
            $event->playerId()->toString(),
            $event->outcome()->value,
            $event->stake()->amount(),
            $event->payoutMinor(),
            $event->stake()->currency()->value,
            $event->occurredAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            $event->tenantId()->toString(),
        );
        $this->bus->dispatch($message);
    }
}
