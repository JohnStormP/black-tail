<?php

namespace App\Risk\Infrastructure\EventListener;

use App\Risk\Application\Message\LogRiskSignalRaisedMessage;
use App\Risk\Domain\Event\RiskSignalRaised;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class DispatchLogRiskSignalRaisedMessageListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(RiskSignalRaised $event): void
    {
        $this->bus->dispatch(new LogRiskSignalRaisedMessage(
            $event->tenantId()->toString(),
            $event->playerId()->toString(),
            $event->operation()->value,
            $event->action()->value,
            array_map(static fn ($id) => $id->toString(), $event->matchedRuleIds())
        ));
    }
}
