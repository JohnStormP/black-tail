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
ORDER BY (toDate(occurred_at), bet_id);
