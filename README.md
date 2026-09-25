# BLACK-TAIL

B2B iGaming platform backend (portfolio / demo).
Simulates the infrastructure behind an online casino:
players, multi-tenancy, wallets, ledger, deposits/withdrawals, games, betting, bonuses, payments, risk,
analytics, real-time events, and an AI analytics assistant — without real money or real gambling providers.

## Stack

- PHP 8.4+
- Symfony 8.1
- API Platform
- Doctrine ORM
- PostgreSQL
- Redis
- RabbitMQ (Symfony Messenger)
- ClickHouse
- Mercure / WebSockets
- Docker (Nginx, PHP-FPM)
- PHPUnit
- PHPStan
- PHP CS Fixer
- GitHub Actions (CI)
- LLM integration (AI Analyst)

## Development

Docker stack must be running (`docker compose up -d`). Quality commands go through the `php` service:

```bash
make help              # list targets
make test              # PHPUnit
make phpstan
make cs                # PHP CS Fixer dry-run
make cs-fix            # apply CS fixes
make check             # test + phpstan + cs + lint:container
make schema-validate   # Doctrine schema (test env)
```
