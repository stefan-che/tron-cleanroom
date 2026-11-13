# Quickstart — run the stack from scratch on one host

> This guide brings the whole stack up on a single host for evaluation and development. For production, run the application database and the key vault on separate hosts with separate credentials — see [SECURITY.md](../SECURITY.md).

[README](../README.md) · [Architecture](../ARCHITECTURE.md) · [API reference](API_REFERENCE.md)

Three repositories are required; a fourth, Tally (`tally-bot`), is optional:

| Repo | Runs as | Role |
|---|---|---|
| `tron-cleanroom` | docker stack in `tron-cleanroom/docker` | Laravel app: wallets, AML, energy, admin, bots, REST API |
| `cleanroom-watcher` | docker stack in `cleanroom-watcher/` | watches TRON, sends webhooks for incoming transfers |
| `cleanroom-vault` | binary `tron-cleanroom/enc/encryptor` + vault MySQL | seals the private keys of transit wallets |
| `tally-bot` (optional) | docker stack in `tally-bot/` | Tally: team ledger in Telegram, balances of watched wallets |

```
 your backend ──REST──► tron-cleanroom ──/api/watch──► cleanroom-watcher ──► TRON
                          │      ▲                          │
                          │      └── webhook (HMAC) ────────┘
                          └─► enc/encryptor ─► vault MySQL (app-mysql-key-storage)
```

## 1. Prerequisites

- Docker Engine with Compose v2, GNU make, git, curl. Nothing else: PHP and Go run in containers
  (`php:8.4`, `golang:1.25`).
- Free host ports (all overridable, see step 5): 80/443 (admin + API), 127.0.0.1:3306, 3307, 5432,
  6380 (processing DBs), 127.0.0.1:8081 (listener API).
- For real payments: a public HTTPS domain pointing to this host (Telegram webhooks, your backend),
  TRON access (own node or a TronGrid API key), AML / energy provider accounts.

## 2. Clone side by side

```bash
mkdir crypto-stack && cd crypto-stack
git clone https://github.com/stefan-che/tron-cleanroom.git
git clone https://github.com/stefan-che/cleanroom-watcher.git
git clone https://github.com/stefan-che/cleanroom-vault.git
git clone https://github.com/stefan-che/tally-bot.git      # optional: team bookkeeping
```

The processing stack expects `../cleanroom-vault` next to itself (encryptor source and the vault schema
`cleanroom-vault/db.sql`). Another location: set `ENCRYPTOR_SRC` in `tron-cleanroom/docker/.env`
(path relative to `tron-cleanroom/docker`).

## 3. Shared network

```bash
docker network create processing-net     # or: make network (in either repo)
```

On this network processing calls the listener as `http://cleanroom-watcher:8080`, and the listener reaches
processing as `http://tron-cleanroom` (nginx alias). Another name: `SHARED_NETWORK` in both stacks.

## 4. cleanroom-watcher

```bash
cd cleanroom-watcher
make config            # config.json from config.docker.json.example (db host mysql, redis host redis)
```

Edit `config.json`:

- `api.api_keys` — **must** contain at least one random key (`openssl rand -hex 32`); cleanroom-watcher
  authenticates API calls against this list. Put the same value into `WALLET_LISTENER_WEBHOOK_SECRET` of
  processing (step 5).
- `scanner.start_block` — a **recent** block number. `0` on an empty database means "scan from
  genesis". Current block: `curl -s -X POST https://api.trongrid.io/wallet/getnowblock` →
  `block_header.raw_data.number`.
- `tron_nodes` — your node and/or `https://api.trongrid.io` with an API key (without a key TronGrid
  throttles requests).
- `admin.path`, `admin.password`, `admin.session_secret` — your own values (admin UI at
  `http://127.0.0.1:8081/<path>`).
- `webhooks` stays `[]`: processing registers its own webhook with every address.
- DB passwords: defaults are `change-me`; to change, export `LISTENER_DB_PASSWORD` /
  `LISTENER_DB_ROOT_PASSWORD` and edit `db_dsn` accordingly.

```bash
make up                                   # LISTENER_HTTP_PORT=8081 by default
curl -s http://127.0.0.1:8081/health      # {"status":"ok","mysql":"ok","redis":"ok",...}
```

## 5. tron-cleanroom: env files

```bash
cd ../tron-cleanroom/docker
make env               # copies ../.env.example -> ../.env and .env.example -> .env if missing
```

**`docker/.env`** (containers). Required: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`DB_ROOT_PASSWORD`, `DB_PASSWORD_KEY_STORAGE` (vault user `enc_user`; no `'` or `\`),
`REDIS_PASSWORD`. Optional with defaults: `HTTP_PORT` 80, `HTTPS_PORT` 443, `MYSQL_PUBLISH_PORT` 3306,
`MYSQL_KS_PUBLISH_PORT` 3307, `POSTGRES_PUBLISH_PORT` 5432, `REDIS_PUBLISH_PORT` 6380,
`ENCRYPTOR_SRC` `../../cleanroom-vault`, `SHARED_NETWORK` `processing-net`, `ENVIRONMENT` `local`.
The vault database name `encryptor` and user `enc_user` are fixed by `cleanroom-vault/db.sql`;
`DB_DATABASE_KEY_STORAGE` / `DB_USERNAME_KEY_STORAGE` are no longer used.

**`.env`** (Laravel) — names only, grouped:

| Group | Variables |
|---|---|
| Core | `APP_URL`, `APP_KEY` (leave empty, bootstrap generates it), `APP_ENV`, `APP_TIMEZONE`, `DASHBOARD_PREFIX` (default `/admin`), `GLOBAL_OUR_API_KEY`, `GLOBAL_READONLY_API_KEY` |
| DB / Redis | `DB_CONNECTION=mysql`, `DB_HOST=app-mysql`, `DB_PORT=3306`, `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` (same as docker/.env), `REDIS_HOST=app-redis`, `REDIS_PORT=6379`, `REDIS_PASSWORD`, `QUEUE_CONNECTION`, `CACHE_STORE`; key backup DB `BACKUP_DB_*` (optional) |
| Listener | `WALLET_LISTENER_SERVICE_URL=http://cleanroom-watcher:8080`, `WALLET_LISTENER_WEBHOOK_SECRET` (= a key from listener `api.api_keys`, sent as `X-API-Key`), `WALLET_LISTENER_HMAC_SECRET` (new; random, signs listener webhooks; empty = every webhook is rejected) |
| TRON | `TRON_FULL_NODE`, `TRON_SOLIDITY_NODE`, `TRON_EVENT_SERVER`, `TRON_API_KEY`, `TRON_2_*`, `TRONGRID_FULL_NODE`, `TRONGRID_SOLIDITY_NODE`, `TRONGRID_EVENT_SERVER`, `TRONGRID_API_KEY` |
| Telegram | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_SECRET_TOKEN`, `TELEGRAM_OPERATIONS_BOT_TOKEN`, `TELEGRAM_OPERATIONS_CHAT_ID`, `TELEGRAM_OPERATIONS_SECRET_TOKEN` |
| AML | `AML_ACCESS_ID`, `AML_ACCESS_KEY` (AMLBot), `WELL_WALLET_API_KEY`, `WELL_WALLET_BASE_URL`, `AML_GETBLOCK_TOKEN`, `AML_BITOK_KEY_ID`, `AML_BITOK_SECRET_KEY`, `AML_COINKYT_API_KEY`, `AML_COINKYT_RISK_PROFILE_ID`, `AML_COINKYT_RECIPIENTS` |
| AI verdict / energy | `OPENAI_API_KEY`, `OPENAI_MODEL`; `REFEE_TOKEN`, `TR_ENERGY_API_KEY`, `NETTS_API_KEY` |

`APP_URL` is used when the app builds URLs outside an HTTP request — the webhook URL it hands to the
listener and the Telegram webhook URL. Pick by setup:

- public host: `APP_URL=https://pay.example.com` (the listener must be able to reach it);
- local / no domain: `APP_URL=http://tron-cleanroom` (reachable from the listener over
  `processing-net`); use Telegram polling (step 8) instead of webhooks.

Fill the TRON variables **before** bootstrap: the seeder creates `tron_nodes` rows only for
configured nodes (re-run `make seed` later to add them; existing rows are not overwritten).

## 6. tron-cleanroom: bootstrap

```bash
make bootstrap
```

It runs, and is safe to re-run (never overwrites env files, `enc/kek`, `enc/config.json`, `APP_KEY`):

1. `env`, `check-env` (stops with the list of empty required `docker/.env` variables), `network`;
2. `enc` — builds `enc/encryptor` from `../cleanroom-vault` in `golang:1.25`, static, for the docker
   engine's architecture;
3. `enc-init` — creates `enc/kek` (random KEK, mode 600) and `enc/config.json` from
   `enc/config.json.example`: a random master key wrapped with the KEK, and the vault DSN
   `enc_user:…@tcp(app-mysql-key-storage:3306)/encryptor` encrypted into `connect3` (default) and
   `connect4` (test wallets). **The master key is printed once — store it offline.**
4. `docker compose up -d --build` — the vault container `app-mysql-key-storage` applies
   `cleanroom-vault/db.sql` on its first start (password `DB_PASSWORD_KEY_STORAGE`) and reports healthy
   only when `encryptor.wallet_keys` exists;
5. `composer install` (PHP 8.4, no platform flags), `key:generate` if `APP_KEY` is empty,
   `migrate`, `db:seed`, restart of queue workers and scheduler;
6. `enc-check` — one encrypt/decrypt round trip through `KeyManager` → `enc/encryptor` → vault.

Vault init failed (wrong password, missing `cleanroom-vault`)? The init script runs only on an empty
data directory: fix `docker/.env`, then `docker compose down`, remove `docker/data/mysql-ks/`, and
re-run `make bootstrap`.

The KEK is read only from `enc/kek` next to the binary; there is no `ENCRYPTOR_KEK` environment
variable (the override in `cleanroom-vault/main.go` is disabled on purpose).

## 7. Admin user and first configuration

```bash
docker compose exec app-php php artisan orchid:admin admin admin@example.com '<password>'
```

Open `http://localhost${DASHBOARD_PREFIX}` (default `/admin`; add `:HTTP_PORT` if changed), sign in,
enable 2FA in the profile. Then:

1. **Recipients → Create recipient.** Set `external_id` (or leave empty for a UUID) — this is the
   `recipient_id` of the API.
2. **Recipient → tab "Wallets".** Add one destination wallet per colour for each chain:
   **WHITE**, **YELLOW**, **GRAY**. Incoming (`buy`) money of these colours is sent **only** to the
   recipient's wallet of the same colour. **BLACK** money may go to a free address that the operator supplies
   (for example a return address); the stack does not look up the sender.
3. **Recipients → Users** (Telegram users): add a user by Telegram ID, then bind it to the recipient
   in the recipient's tab "Users (telegram)". Users with "can create recipients" can also create
   recipients from the bot.
4. **Settings:** API IP allow-list (fill it before exposing the host), auto-send, AML/energy/AI providers (enable flag
   and priority per provider in `aml_services`, `energy_services`, `ai_services`).

## 8. Telegram bots

Create bots with @BotFather; set `TELEGRAM_BOT_TOKEN` (recipient bot) and
`TELEGRAM_OPERATIONS_BOT_TOKEN` (operations bot), the chat IDs for alerts, and random
`TELEGRAM_SECRET_TOKEN` / `TELEGRAM_OPERATIONS_SECRET_TOKEN`.

- **Public HTTPS host:** `docker compose exec app-php php artisan set-wh-telegram:run` registers
  `APP_URL/telegram/webhook` and `APP_URL/telegram/operations/webhook` (shows a preview, asks to
  confirm). Check: `php artisan wh-info-telegram:run`.
- **Local, no domain:** run polling instead: `docker compose exec app-php php artisan telegram:poll`
  and, in another terminal, `php artisan operations-telegram:poll`.

**Optional — ledger.** To link recipient wallets to an external accounts ledger, set
`LEDGER_API_URL` (e.g. `http://tally-bot:8080`) and `LEDGER_API_TOKEN`, then check with
`php artisan test:ledger --health`. Skip it otherwise; see [LEDGER_INTEGRATION.md](LEDGER_INTEGRATION.md).

## 9. First transit wallet

```bash
curl -X POST http://localhost/api/wallet/trc-20/generate \
  -H 'X-API-KEY: <GLOBAL_OUR_API_KEY>' -H 'Accept: application/json' \
  -d external_id=order-1 -d recipient_id=<recipient external_id> -d operation_type=buy
```

`X-API-KEY` is checked against `GLOBAL_OUR_API_KEY` (comma-separated list); set it before exposing
the host. Add `-d is_test=1` for a test wallet (vault slot 4).

## 10. What happens next

1. The private key is sealed by `enc/encryptor` (half of the AES key in the vault DB); wallet `NEW`.
2. The queue registers the address in cleanroom-watcher (`POST /api/watch`, `X-API-Key`), with the webhook
   `APP_URL/webhook/wallet-listener` and the HMAC secret.
3. The customer pays; the listener sends a signed webhook (`X-Signature: sha256=…`); the scheduler
   also syncs transactions in case a webhook is missed. Wallet → `ADD_TRANSACTION`.
4. AML checks run in all enabled providers; the result (and optional AI verdict) gives a colour.
5. The colour is applied by your backend (`POST /api/wallet/send/{address}/{white|yellow|gray|black}`),
   an operator in the admin panel or the operations bot; energy is rented and USDT goes to the
   recipient's wallet of that colour; then `COMPLETED`. Unpaid wallets expire to `TIME_OUT`.

## 11. Where to look

- Admin: **Operations** (wallets, per-wallet info and logs), **Statistics**, **AML** (checks,
  PDF reports, export), **Reports → AI AML checks**, **TRX wallets**, **Energy auto-replenishment**.
- Telegram: alerts and the daily wallet report go to `TELEGRAM_CHAT_ID` / the operations chat.
- Logs: `storage/logs/` (`worker.log`, `scheduler.log`, channel logs), log viewer in the admin;
  listener: `docker compose logs -f scanner` and its admin UI.

## Notes

- **TRON mainnet.** AML providers and energy markets work with real TRON mainnet addresses and your
  paid accounts; run your first payments with small amounts. Configure the AML and energy keys
  before the first payment: wallets wait for them before the sweep.
- For production, use a TronGrid API key or your own node.
- The local stack runs php-fpm, queue workers and the scheduler in one container with published
  ports, for development. For production use `docker/prod/` (`docker/prod/README.md`) and the
  [hardening checklist](../SECURITY.md).
