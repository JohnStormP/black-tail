<?php

namespace App\Analytics\Infrastructure\Messenger;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use App\Analytics\Application\Message\RecordBetPlacedAnalyticsMessage;
use App\Analytics\Infrastructure\ClickHouse\ClickHouseSchema;
use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RecordBetPlacedAnalyticsMessageHandler
{
    public const string CONSUMER = 'record_bet_placed_analytics';

    public function __construct(
        private readonly ClickHouseClientInterface $clickHouse,
        private readonly ConsumedMessageStoreInterface $consumedMessages,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function __invoke(RecordBetPlacedAnalyticsMessage $message): void
    {
        if (!$this->consumedMessages->claim(self::CONSUMER, $message->betId)) {
            return;
        }

        $row = json_encode([
            'tenant_id' => $message->tenantId,
            'bet_id' => $message->betId,
            'player_id' => $message->playerId,
            'game_id' => $message->gameId,
            'round_id' => $message->roundId,
            'amount_minor' => $message->amountMinor,
            'currency' => $message->currency,
            'occurred_at' => $message->occurredAt,
        ], \JSON_THROW_ON_ERROR);

        $this->clickHouse->execute(
            'INSERT INTO '.ClickHouseSchema::BET_EVENTS_TABLE." FORMAT JSONEachRow\n".$row,
        );
    }
}
