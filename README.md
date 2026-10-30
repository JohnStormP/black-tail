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
make demo              # migrations + app:demo:seed
```

| Service | URL / port |
| --- | --- |
| HTTP (Nginx → PHP-FPM) | http://127.0.0.1:8080 |
| RabbitMQ management | http://127.0.0.1:15672 (`guest` / `guest`) |
| Mercure hub | http://127.0.0.1:3000/.well-known/mercure |
| Postgres | `127.0.0.1:5432` |
| Redis | `127.0.0.1:6379` |
| ClickHouse HTTP | `127.0.0.1:8124` (container listens on 8123) |

Demo login (from `app:demo:seed` / `DemoSeedIds`):

- email: `admin@blacktail.demo`
- password: `secret-password`
- tenant id: `01JC9K2M5N7P8Q3R4S6T7V8W9X`

```bash
curl -i -c cookies.txt -X POST http://127.0.0.1:8080/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@blacktail.demo","password":"secret-password"}'
```

Session cookie is set on success. Use it for authenticated calls; many tenant-scoped endpoints also need `tenantId` in the body or query (see [docs/api.md](docs/api.md)).

`.env` is tuned for PHP **inside** Compose (hosts `postgres`, `redis`, `clickhouse`, …). For PHP on the host (`symfony serve`, host PHPUnit), put `127.0.0.1` overrides in `.env.local` and use ClickHouse port **8124**. Do not commit `.env.local`.

## Development

Stack must be running (`make up`). Quality commands go through the `php` service:

```bash
make help              # list targets
make up                # build + start stack
make demo              # migrate + seed
make test              # PHPUnit
make phpstan
make cs                # PHP CS Fixer dry-run
make cs-fix            # apply CS fixes
make check             # test + phpstan + cs + lint:container
make schema-validate   # Doctrine schema (test env)
```

HTTP API map: [docs/api.md](docs/api.md).
