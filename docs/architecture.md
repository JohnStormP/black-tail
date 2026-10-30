# Architecture

BLACK-TAIL is a **Symfony modular monolith**. Bounded contexts live under `src/<Module>/` with the usual layers:

- **Domain** - entities, value objects, domain events, repository ports, domain exceptions
- **Application** - commands/queries and handlers (use-cases)
- **Infrastructure** - Doctrine repos, HTTP controllers, Messenger listeners, external clients

Cross-cutting pieces (demo seed, request id, caches, ClickHouse client wiring) sit in **Shared**. HTTP is thin: controllers map the request, call a handler, return JSON. Persistence for transactional data is **PostgreSQL**; analytics facts go to **ClickHouse** via async Messenger (RabbitMQ). **Redis** backs cache and rate limiting. **Mercure** publishes real-time crash flight updates.

## Module map

```mermaid
flowchart TB
  subgraph http [HTTP Nginx / PHP-FPM]
    API[Controllers]
  end

  subgraph modules [Domain modules]
    Identity
    Tenant
    Player
    Wallet
    Payment
    Betting
    Game
    Bonus
    Risk
    Analytics
    AI
    Shared
  end

  subgraph infra [Infrastructure]
    PG[(PostgreSQL)]
    Redis[(Redis)]
    RMQ[[RabbitMQ]]
    CH[(ClickHouse)]
    Mercure[[Mercure]]
    LLM[LLM client / Fake]
  end

  API --> Identity
  API --> Tenant
  API --> Player
  API --> Wallet
  API --> Payment
  API --> Betting
  API --> Game
  API --> Bonus
  API --> Risk
  API --> Analytics
  API --> AI

  Betting --> Wallet
  Payment --> Wallet
  Game --> Wallet
  Bonus --> Wallet
  AI --> Analytics

  Identity --> PG
  Tenant --> PG
  Player --> PG
  Wallet --> PG
  Payment --> PG
  Betting --> PG
  Game --> PG
  Bonus --> PG
  Risk --> PG

  Betting -.->|domain events| RMQ
  Payment -.->|domain events| RMQ
  Risk -.->|domain events| RMQ
  RMQ --> Analytics
  Analytics --> CH

  Game --> Mercure
  Shared --> Redis
  AI --> LLM
```

Edges are the important runtime dependencies only, not a full import graph.

## Key flows

### Bet accept / settle

1. Client places a bet (`POST /api/bets`), then accepts it (`POST /api/bets/{id}/accept`) with wallet + reserve ledger ids.
2. `AcceptBetHandler` reserves funds via Wallet (`ReserveWallet`).
3. Settle (`POST /api/bets/{id}/settle`) marks won/lost and settles or releases the wallet hold.
4. Domain events fan out through Messenger: analytics records (ClickHouse), optional bonus wagering / risk side effects.

### Payment deposit / withdraw

1. Deposit (`POST /api/payments/deposits`) or withdrawal (`POST /api/payments/withdrawals`) creates a payment with an idempotency key.
2. Handlers drive Wallet ledger entries (credit on deposit success; reserve/release on withdraw).
3. Provider webhook / fail-stale paths update payment status without double-applying money (unique constraints + idempotent handlers).

### Crash cash-out

1. Crash round lifecycle lives in **Game** (`Open` / `Start` / `PublishFlight` / `Resolve`).
2. Player places a crash bet, then cashes out (`CashOutCrashBetHandler`) against the current multiplier.
3. Wallet settle/payout runs in the same use-case path; flight updates can be pushed via Mercure for the UI.

### AI ask

1. `POST /api/ai/ask` is rate-limited per authenticated user.
2. `AskAnalyticsQuestionHandler` builds a short analytics context (aggregates from Analytics / ClickHouse reads).
3. Question goes to an LLM client; default wiring is **FakeLlmClient** so demo works without an API key. Real OpenAI-compatible URL/key are optional via env.

## HTTP map

Endpoint table: [api.md](api.md).
