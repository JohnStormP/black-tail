<?php

namespace App\Betting\Infrastructure\Messenger;

use App\Betting\Application\Message\LogBetPlacedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LogBetPlacedMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LogBetPlacedMessage $message): void
    {
        $this->logger->info('Bet placed', [
            'bet_id' => $message->betId,
            'player_id' => $message->playerId,
            'amount' => $message->amountMinor,
            'currency' => $message->currency,
        ]);
    }
}
