<?php

namespace App\Betting\Infrastructure\EventListener;

use App\Betting\Domain\Event\BetPlaced;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class LogBetPlacedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(BetPlaced $event): void
    {
        $this->logger->info('Bet placed', [
            'bet_id' => $event->betId()->toString(),
            'player_id' => $event->playerId()->toString(),
            'amount' => $event->amount()->amount(),
            'currency' => $event->amount()->currency()->value,
        ]);
    }
}
