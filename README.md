# BLACK-TAIL

B2B iGaming platform backend (portfolio / demo).
Simulates the infrastructure behind an online casino:
players, multi-tenancy, wallets, ledger, deposits/withdrawals, games, betting, bonuses, payments, risk,
analytics, real-time events, and an AI analytics assistant - without real money or real gambling providers.

## Stack

- PHP 8.4+
- Symfony 8.1
- Doctrine ORM
- PostgreSQL
- Redis
- RabbitMQ (Symfony Messenger)
- ClickHouse
- Mercure
- Docker (Nginx, PHP-FPM)
- PHPUnit
- PHPStan
- PHP CS Fixer
- GitHub Actions (CI)
- LLM integration (AI Analyst)

## Quickstart (demo)

Prerequisites: Docker and Docker Compose.

```bash
cp .env.example .env   # if .env is missing
make up                # build + start stack
make demo              # migrations + ClickHouse schema + app:demo:seed
```

| Service | URL / port |
| --- | --- |
| HTTP (Nginx → PHP-FPM) | http://127.0.0.1:8080 |
| Worker (`messenger:consume async`) | Compose service `worker` (no host port) |
| RabbitMQ management | http://127.0.0.1:15672 (`guest` / `guest`) |
| Mercure hub | http://127.0.0.1:3000/.well-known/mercure |
| Postgres | `127.0.0.1:5432` |
| Redis | `127.0.0.1:6379` |
| ClickHouse HTTP | `127.0.0.1:8124` (container listens on 8123) |

Demo login (from `app:demo:seed` / `DemoSeedIds`):

- tenant admin: `admin@blacktail.demo` / `secret-password`
- super admin: `super@blacktail.demo` / `secret-password` (open/start Crash)
- tenant id: `01JC9K2M5N7P8Q3R4S6T7V8W9X`
- seeded games: `lucky_reels`, `crash`

```bash
curl -i -c cookies.txt -X POST http://127.0.0.1:8080/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@blacktail.demo","password":"secret-password"}'
```

Session cookie is set on success. Use it for authenticated calls; many tenant-scoped endpoints also need `tenantId` in the body or query (see [docs/api.md](docs/api.md)).

`.env` is tuned for PHP **inside** Compose (hosts `postgres`, `redis`, `clickhouse`, …). For PHP on the host (`symfony serve`, host PHPUnit), put `127.0.0.1` overrides in `.env.local` and use ClickHouse port **8124**. Do not commit `.env.local`.

## Architecture

Modular monolith under `src/`. Each module keeps Domain / Application / Infrastructure. Details and Mermaid diagram: [docs/architecture.md](docs/architecture.md).

| Module | Role |
| --- | --- |
| Identity | Users, session login/logout, roles |
| Tenant | Multi-tenant registry and lifecycle |
| Player | Player accounts scoped to a tenant |
| Wallet | Balances, ledger, reserve / settle / release |
| Payment | Deposits and withdrawals (idempotent) |
| Betting | Place / accept / settle / cancel / refund bets |
| Game | Catalog, tenant games, Lucky Reels, Crash + Mercure |
| Bonus | Campaigns, grants, wagering |
| Risk | Rules and signals |
| Analytics | Aggregates (GGR, bets by day) via ClickHouse |
| AI | Natural-language ask over analytics context |
| Shared | Demo seed, request id, caches, shared infra |

### Flows (short)

1. **Bet accept / settle** - accept reserves wallet funds; settle wins/losses and releases or pays out; domain events feed analytics (and related side effects).
2. **Payment deposit / withdraw** - payment use-case + idempotency key drives wallet ledger entries; webhooks / fail-stale must not double-apply money.
3. **Crash cash-out** - Game owns the round and cash-out against the multiplier; wallet settle runs in that path; flights can go out over Mercure.
4. **AI ask** - rate-limited `/api/ai/ask` builds analytics context, then calls an LLM client (Fake by default for local demo).

`frontend/` is a separate SPA client for this HTTP API on `:8080`.

HTTP API map: [docs/api.md](docs/api.md).

## Development

Stack must be running (`make up`). Quality commands go through the `php` service:

```bash
make help              # list targets
make up                # build + start stack
make demo              # migrate + ClickHouse schema + seed
make test              # PHPUnit
make phpstan
make cs                # PHP CS Fixer dry-run
make cs-fix            # apply CS fixes
make check             # test + phpstan + cs + lint:container
make schema-validate   # Doctrine schema (test env)
```
