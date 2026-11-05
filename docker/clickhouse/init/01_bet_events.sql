CREATE TABLE IF NOT EXISTS bet_events
(
    tenant_id String,
    bet_id String,
    player_id String,
    game_id String,
    round_id String,
    amount_minor Int64,
    currency LowCardinality(String),
    occurred_at DateTime64(3, 'UTC')
)
ENGINE = MergeTree
ORDER BY (tenant_id, toDate(occurred_at), bet_id);

CREATE TABLE IF NOT EXISTS bet_settled_events
(
    tenant_id String,
    bet_id String,
    player_id String,
    outcome LowCardinality(String),
    stake_minor Int64,
    payout_minor Int64,
    currency LowCardinality(String),
    occurred_at DateTime64(3, 'UTC')
)
ENGINE = MergeTree
ORDER BY (tenant_id, toDate(occurred_at), bet_id);
