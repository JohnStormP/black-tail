<?php

namespace App\Analytics\Infrastructure\ClickHouse;

final class ClickHouseSchema
{
    public const string BET_EVENTS_TABLE = 'bet_events';
    public const string BET_EVENTS_DDL = <<<'CH'
CREATE TABLE IF NOT EXISTS bet_events
(
    bet_id String,
    player_id String,
    game_id String,
    round_id String,
    amount_minor Int64,
    currency LowCardinality(String),
    occurred_at DateTime64(3, 'UTC')
)
ENGINE = MergeTree
ORDER BY (toDate(occurred_at), bet_id)
CH;

    public const string BET_SETTLED_EVENTS_TABLE = 'bet_settled_events';
    public const string BET_SETTLED_EVENTS_DDL = <<<'CH'
CREATE TABLE IF NOT EXISTS bet_settled_events
(
    bet_id String,
    player_id String,
    outcome LowCardinality(String),
    stake_minor Int64,
    payout_minor Int64,
    currency LowCardinality(String),
    occurred_at DateTime64(3, 'UTC')
)
ENGINE = MergeTree
ORDER BY (toDate(occurred_at), bet_id)
CH;
}
