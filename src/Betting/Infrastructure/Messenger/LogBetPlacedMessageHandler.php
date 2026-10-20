<?php

namespace App\Betting\Infrastructure\Messenger;

use App\Betting\Application\Message\LogBetPlacedMessage;
use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LogBetPlacedMessageHandler
{
    public const CONSUMER = 'log_bet_placed';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConsumedMessageStoreInterface $consumedMessages,
    ) {
    }

    public function __invoke(LogBetPlacedMessage $message): void
    {
        if ($this->consumedMessages->claim(self::CONSUMER, $message->betId)) {
            $this->logger->info('Bet placed', [
                'bet_id' => $message->betId,
                'player_id' => $message->playerId,
                'amount' => $message->amountMinor,
                'currency' => $message->currency,
            ]);
        }
    }
}
