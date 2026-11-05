# BLACK-TAIL

B2B iGaming platform (portfolio / demo): Symfony modular monolith + React operator console.
Simulates the infrastructure behind an online casino:
players, multi-tenancy, wallets, ledger, deposits/withdrawals, games, betting, bonuses, payments, risk,
analytics, real-time events, and an AI analytics assistant - without real money or real gambling providers.

This is a learning demo, not a production casino. Games in the UI use a seeded demo player and wallet.
The React operator console (`frontend/`) was built with an AI coding agent, then reviewed and adjusted by hand.

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
- React + TypeScript + Vite (`frontend/`)
- PHPUnit
- PHPStan
- PHP CS Fixer
- GitHub Actions (CI)
- LLM integration (AI Analyst)

## Quickstart (demo)

Prerequisites: Docker, Docker Compose, and Node.js 20+ (for the console).

```bash
cp .env.example .env   # if .env is missing
make up                # build + start stack
make install           # Composer deps in the php image
make demo              # migrations + ClickHouse schema + app:demo:seed
make frontend-install  # npm deps
make frontend-dev      # Vite on :5173, proxies /api → :8080
```

| Service | URL / port |
| --- | --- |
| Operator console (Vite) | http://localhost:5173 |
| HTTP API (Nginx → PHP-FPM) | http://127.0.0.1:8080 |
| Worker (`messenger:consume async`) | Compose service `worker` (needed for analytics after spins) |
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

API-only login check:

```bash
curl -i -c cookies.txt -X POST http://127.0.0.1:8080/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@blacktail.demo","password":"secret-password"}'
```

Session cookie is set on success. The console uses the same cookie via the Vite `/api` proxy. Many tenant-scoped endpoints also need `tenantId` in the body or query (see [docs/api.md](docs/api.md)). Frontend details: [frontend/README.md](frontend/README.md).

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
| Bonus | Campaigns, grants, wagering (no HTTP API yet; handlers only) |
| Risk | Rules and signals |
| Analytics | Aggregates (GGR, bets by day) via ClickHouse |
| AI | Natural-language ask over analytics context |
| Shared | Demo seed, request id, caches, shared infra |

### Flows (short)

1. **Bet accept / settle** - accept reserves wallet funds; settle wins/losses and releases or pays out; domain events feed analytics (and related side effects). Async path needs the Compose `worker`.
2. **Payment deposit / withdraw** - payment use-case + idempotency key drives wallet ledger entries; webhooks / fail-stale must not double-apply money. Payments do **not** publish to RabbitMQ.
3. **Crash cash-out** - Game owns the round and cash-out against the server multiplier; wallet settle runs in that path; Mercure flight push is demo-only.
4. **AI ask** - rate-limited `/api/ai/ask` builds analytics context, then **ConfigurableLlmClient** (Fake by default; real LLM when `AI_LLM_*` set).
5. **Operator console** - React SPA talks to the live HTTP API (session cookie); Lucky Reels / Crash play the seeded demo player.

HTTP API map: [docs/api.md](docs/api.md).

## Development

Stack must be running (`make up`). Quality commands go through the `php` service:

```bash
make help              # list targets
make up                # build + start stack
make install           # Composer deps (compose run --rm --no-deps php)
make demo              # migrate + ClickHouse schema + seed
make test-db           # create test DB + migrate (same as CI)
make test              # PHPUnit
make phpstan
make cs                # PHP CS Fixer dry-run
make cs-fix            # apply CS fixes
make check             # test + phpstan + cs + lint:container
make schema-validate   # Doctrine schema (test env)
make frontend-install  # npm install in frontend/
make frontend-dev      # Vite on :5173 (API proxy → :8080)
make frontend-test
make frontend-build
```

If HTTP returns **502** from `:8080`, check that `php` and `nginx` are up (`docker compose ps`) and that php-fpm listens on `9000` for the Docker network (image default via `docker.conf`: `listen = 9000`). Then `docker compose restart nginx php`.
