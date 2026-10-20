<?php

namespace App\Betting\Infrastructure\EventListener;

use App\Betting\Application\Message\LogBetPlacedMessage;
use App\Betting\Domain\Event\BetPlaced;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class DispatchLogBetPlacedMessageListener
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
        $this->bus->dispatch(new LogBetPlacedMessage(
            $event->betId()->toString(),
            $event->playerId()->toString(),
            $event->amount()->amount(),
            $event->amount()->currency()->value,
        ));
    }
}
