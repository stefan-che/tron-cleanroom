# Configuration

Environment variables of the Laravel app, set in `.env` (start from `.env.example`; `make env`
copies it). Names and meaning only: no values here. Container settings (ports, DB root password,
vault password, `ENCRYPTOR_SRC`) live in `docker/.env` (template `docker/.env.example`), not here.

**Required** = the payment path does not work without it. **Optional** = a feature is off or a
default applies when empty. Some provider settings (enable flag, priority, AML threshold, API IP
allow-list) live in database tables and are edited in the admin panel, not in `.env`.

## App

| Variable | Meaning | |
|---|---|---|
| `APP_KEY` | Laravel encryption key; `make bootstrap` generates it | required |
| `APP_URL` | Public base URL; cleanroom-watcher calls `APP_URL/webhook/wallet-listener` | required |
| `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Standard Laravel app settings; keep `APP_DEBUG=false` outside development | optional |
| `DASHBOARD_PREFIX`, `DASHBOARD_DOMAIN` | Admin panel URL prefix (default `/admin`) and optional domain | optional |
| `GLOBAL_OUR_API_KEY` | Comma-separated API keys sent as `X-API-KEY`; set before exposing the API | required |
| `GLOBAL_READONLY_API_KEY` | Key for read-only API routes | optional |
| `OTP_ENABLED` | Enables Google Authenticator 2FA in the admin panel; each user turns it on in the profile | recommended |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | Laravel logging | optional |
| `SEED_CLEAR`, `SEED_TRANSACTION_LIMIT` | Test seeder only | optional |

## Log viewer

| Variable | Meaning | |
|---|---|---|
| `LOG_VIEWER_ENABLED` | Turns the web log viewer on or off | optional |
| `LOG_VIEWER_ROUTE_PATH` | Its URL path (default `/log-viewer`); set a private value in production | optional |
| `LOG_VIEWER_USERNAME`, `LOG_VIEWER_PASSWORD` | Credentials for the optional `LogViewerBasicAuth` middleware (switched on in `config/log-viewer.php`); see [SECURITY.md](../SECURITY.md) | optional |

## Databases

| Variable | Meaning | |
|---|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Main MySQL database; same values as in `docker/.env` | required |
| `WALLET_KEY_BACKUP_ENABLED` | Copies encrypted wallet keys to a second database | optional |
| `BACKUP_DB_HOST`, `BACKUP_DB_PORT`, `BACKUP_DB_DATABASE`, `BACKUP_DB_USERNAME`, `BACKUP_DB_PASSWORD` | That backup database | optional |

The key vault (cleanroom-vault) has no variables here: `enc/encryptor` reads `enc/kek` and
`enc/config.json`, both created by `make bootstrap`. See [enc/README.md](../enc/README.md).

## Redis, queue, cache, session

| Variable | Meaning | |
|---|---|---|
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Redis connection; password as in `docker/.env` | required |
| `QUEUE_CONNECTION`, `CACHE_STORE` | Queue and cache backends (Redis by default) | required |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Admin sessions | optional |

## TRON

| Variable | Meaning | |
|---|---|---|
| `TRON_FULL_NODE`, `TRON_SOLIDITY_NODE`, `TRON_EVENT_SERVER`, `TRON_API_KEY` | Your first node; seeds the `tron_nodes` table | one node source required |
| `TRON_2_*` (same four) | Second node | optional |
| `TRONGRID_FULL_NODE`, `TRONGRID_SOLIDITY_NODE`, `TRONGRID_EVENT_SERVER`, `TRONGRID_API_KEY` | TronGrid, used last in failover | one node source required |

After seeding, the `tron_nodes` table is the source of truth; edit nodes in the admin panel.

## cleanroom-watcher

| Variable | Meaning | |
|---|---|---|
| `WALLET_LISTENER_SERVICE_URL` | cleanroom-watcher base URL | required |
| `WALLET_LISTENER_WEBHOOK_SECRET` | cleanroom-watcher **API key**, sent as `X-API-Key` (the name is historical) | required |
| `WALLET_LISTENER_HMAC_SECRET` | Secret that signs listener webhooks. **Empty = every webhook is rejected** | required |

## AML providers

| Variable | Meaning | |
|---|---|---|
| `AML_ACCESS_ID`, `AML_ACCESS_KEY` | AMLBot credentials; the key also verifies AMLBot webhooks. AMLBot is enabled in the seed | required for AML |
| `AML_WEBHOOK_PATH` | Path of the inbound AMLBot webhook (default `/aml/webhook`); use a hard-to-guess value and register it at AMLBot | optional |
| `WELL_WALLET_BASE_URL`, `WELL_WALLET_API_KEY` | WellWallet | optional |
| `AML_GETBLOCK_TOKEN` | GetBlock | optional |
| `AML_BITOK_KEY_ID`, `AML_BITOK_SECRET_KEY` | BitOK | optional |
| `AML_COINKYT_API_KEY`, `AML_COINKYT_REPORT_LANGUAGE`, `AML_COINKYT_RISK_PROFILE_ID` | CoinKYT | optional |
| `AML_COINKYT_RECIPIENTS` | Comma-separated recipient `external_id`s that use CoinKYT; empty = all | optional |
| `RECURSIVE_AML_ENABLED`, `RECURSIVE_AML_URL`, `RECURSIVE_AML_API_KEY` | External counterparty-graph service | optional |
| `AML_EXPORT_SOURCE` | Source label written into AML export manifests | optional |

Optional providers stay inactive until enabled in the admin panel (`aml_services` table).

## AI risk tier

| Variable | Meaning | |
|---|---|---|
| `OPENAI_API_KEY`, `OPENAI_MODEL` | OpenAI, used for the optional advisory tier (off by default, switched in settings) | optional |
| `OPENAI_ADMIN_API_KEY` | OpenAI Admin API key, only for spend statistics | optional |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_ADMIN_API_KEY`, `CLAUDE_MODEL` | Reserved in config; the AI tier uses OpenAI | optional |

## Energy providers

| Variable | Meaning | |
|---|---|---|
| `REFEE_TOKEN` | ReFee (enabled in the seed) | required for sweeps |
| `TR_ENERGY_API_KEY`, `NETTS_API_KEY` | Tr.energy, Netts | optional |

## Block explorers (ad-hoc screening on other chains)

`ETHERSCAN_API_KEY`, `BSCSCAN_API_KEY`, `POLYGONSCAN_API_KEY`, `ARBISCAN_API_KEY`,
`OPTIMISM_ETHERSCAN_API_KEY`, `BASESCAN_API_KEY`, `SNOWTRACE_API_KEY`, `BLOCKCYPHER_TOKEN` — all optional.

## Telegram

| Variable | Meaning | |
|---|---|---|
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | Recipient bot and alert chat | optional |
| `TELEGRAM_SECRET_TOKEN`, `TELEGRAM_WEBHOOK_URL`, `TELEGRAM_CERTIFICATE_PATH` | Webhook mode for that bot; the secret token is checked on every update | optional |
| `TELEGRAM_OPERATIONS_BOT_TOKEN`, `TELEGRAM_OPERATIONS_CHAT_ID`, `TELEGRAM_OPERATIONS_SECRET_TOKEN`, `TELEGRAM_OPERATIONS_WEBHOOK_URL` | Operations bot | optional |
| `TELEGRAM_ASYNC_REQUESTS` | Async Telegram API calls | optional |

For local development without webhooks: `php artisan telegram:poll` and `php artisan operations-telegram:poll`.

## Ledger integration

| Variable | Meaning | |
|---|---|---|
| `LEDGER_API_URL`, `LEDGER_API_TOKEN` | tally-bot API and its token (sent as `X-API-KEY`). Empty = integration off, `/api/ledger-accounts/*` returns 503 | optional |

Details: [LEDGER_INTEGRATION.md](LEDGER_INTEGRATION.md).

## Other Laravel variables

Standard Laravel settings (mail, filesystems, AWS, alternative queue and cache drivers) are read
in `config/*.php` and are not needed for the payment path.
