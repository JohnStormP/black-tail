# BLACK-TAIL HTTP API map

Source of truth: Symfony attribute routes (`bin/console debug:router`). Auth is session cookie via `json_login` unless noted.

**Auth legend**

| Tag | Meaning |
|-----|---------|
| public | no login |
| auth | `IS_AUTHENTICATED_FULLY` |
| tenant | auth + `TENANT_ACCESS` on the relevant tenant/player |
| super | `ROLE_SUPER_ADMIN` |
| ai | `AI_ASK` (analyst / tenant_admin / super_admin) |

ULIDs are 26-char Crockford. Money amounts are integer minor units. Currencies: `USD`, `EUR`, `UAH`.

---

## Auth / session

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| POST | `/api/login` | public | `{ email, password }` | session cookie on success; **429** after failed-attempt throttle |
| ANY | `/api/logout` | public | - | JSON 204 |
| GET | `/api/me` | auth | - | current user |

## Ops probes

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/health` | public | liveness |
| GET | `/ready` | public | readiness |

## Tenants

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/tenants` | auth | - | scoped list for current user |
| POST | `/api/tenants` | super | `{ tenantId, name }` | 201 |
| GET | `/api/tenants/{tenantId}` | tenant | - | |

## Players

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/players` | tenant | `?tenantId=` required | |
| POST | `/api/players` | tenant | `{ playerId, tenantId, email }` | 201 |
| GET | `/api/players/{playerId}` | tenant | - | |
| POST | `/api/players/{playerId}/block` | tenant | - | 200 |

## Wallets

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/wallets` | tenant | `?playerId=` required | list by player |
| GET | `/api/wallets/{walletId}` | tenant | - | via player of wallet |
| GET | `/api/wallets/{walletId}/ledger` | tenant | - | |

## Games (catalog)

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/games` | auth | - | global catalog |
| GET | `/api/games/{gameId}` | auth | - | |
| POST | `/api/games` | super | `{ gameId, code, name }` | 201 |
| POST | `/api/games/lucky-reels/spin` | tenant | `{ roundId, gameId, playerId, betMinor }` | 201 / 200 idempotent |

## Tenant games

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/tenant-games` | tenant | `?tenantId=` required | |
| POST | `/api/tenant-games` | tenant | `{ tenantGameId, tenantId, gameId }` | 201 / 200 |
| POST | `/api/tenant-games/{tenantGameId}/enable` | tenant | - | |
| POST | `/api/tenant-games/{tenantGameId}/disable` | tenant | - | |

## Crash

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| POST | `/api/crash/rounds` | auth | `{ roundId }` | open round |
| POST | `/api/crash/rounds/{roundId}/start` | auth | - | |
| GET | `/api/crash/rounds/{roundId}/stream` | auth | - | Mercure / stream |
| POST | `/api/crash/bets` | tenant | `{ betId, roundId, playerId, walletId, reserveEntryId, amountMinor, currency }` | |
| POST | `/api/crash/bets/{betId}/cash-out` | tenant | `{ settleEntryId, payoutEntryId, cashOutHundredths }` | always 200 |

## Bets (sportsbook-style)

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| POST | `/api/bets` | tenant | `{ betId, playerId, gameId, roundId, amountMinor, currency, idempotencyKey, providerReference? }` | 201 |
| GET | `/api/bets/{betId}` | tenant | - | |
| POST | `/api/bets/{betId}/accept` | tenant | `{ walletId, reserveEntryId }` | 200; idempotent if already accepted |
| POST | `/api/bets/{betId}/settle` | tenant | `{ walletId, settleEntryId, outcome: won\|lost, payoutMinor?, payoutEntryId? }` | 200 |

## Payments

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/payments` | tenant | `?tenantId=` required; `?type=deposit\|withdrawal` optional | |
| GET | `/api/payments/{paymentId}` | tenant | - | |
| POST | `/api/payments/deposits` | tenant | `{ paymentId, playerId, walletId, amountMinor, currency, idempotencyKey, provider? }` | 201 |
| POST | `/api/payments/withdrawals` | tenant | `{ paymentId, playerId, walletId, amountMinor, currency, idempotencyKey, reserveEntryId, releaseEntryId, provider? }` | 201 / 200 |
| POST | `/api/payments/webhooks/{provider}` | public | `{ providerReference, outcome, ledgerEntryId, releaseEntryId?, failureReason? }` | provider callback |

## Risk rules

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/risk-rules` | tenant | `?tenantId=` required | all statuses |
| POST | `/api/risk-rules` | tenant | `{ ruleId, tenantId, name, type, threshold, action, windowSeconds? }` | 201 draft |
| GET | `/api/risk-rules/{ruleId}` | tenant | - | |
| POST | `/api/risk-rules/{ruleId}/activate` | tenant | - | |
| POST | `/api/risk-rules/{ruleId}/disable` | tenant | - | |

Risk `type`: `daily_deposit_limit`, `daily_betting_limit`, `max_bet`, `max_withdrawal`, `bet_velocity`.  
Risk `action`: `block`, `flag`, `manual_review`.

## Analytics

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| GET | `/api/analytics/bets-by-day` | auth | `?from=Y-m-d&to=Y-m-d` | ClickHouse |
| GET | `/api/analytics/ggr-by-day` | auth | `?from=Y-m-d&to=Y-m-d` | stake − payout |

## AI

| Method | Path | Auth | Body / query | Notes |
|--------|------|------|--------------|-------|
| POST | `/api/ai/ask` | ai | `{ question }` (max 500) | allowlisted intents only; no raw SQL; **429** when rate limited |

---

## Conventions

- JSON request/response; validation failures typically **422**.
- Domain / VO errors often **400** (invalid id/format) or **404** (not found).
- Tenant isolation failures: **403**.
- Idempotency conflicts: **409** where applicable.
- Rate limits: **429** (`/api/login` failed attempts; `/api/ai/ask`).
- Create vs replay: prefer **201** then **200** when the resource already exists with the same payload.
