# CLAUDE.md — tron-cleanroom

Guide for developers and AI agents. Product overview: [README.md](README.md). API: [docs/API_REFERENCE.md](docs/API_REFERENCE.md).

## Mandatory rules

Before changing code, comments or UI texts (Orchid, Telegram bots) follow `rules.md`. On conflict with habit, `rules.md` wins.

@rules.md

## What this is
Core app of a self-hosted crypto payment stack. Per payment it generates a transit wallet (TRON / USDT TRC-20), seals the private key via `enc/encryptor` (built from `../cleanroom-vault`), receives incoming-transfer webhooks from `../cleanroom-watcher`, runs multi-provider AML (+ optional AI verdict), rents TRON energy and sweeps funds to the recipient's destination wallet chosen by colour.

**Stack:** PHP 8.4, Laravel 11, Orchid 14 (admin, 2FA via google2fa), irazasyed/telegram-bot-sdk, iexbase/tron-api, MySQL 8 (app DB + optional `backup_db` for key backups; the encryptor has its own vault MySQL), Redis (queue, cache, bot state), Docker Compose.

## Directory map
| Need | Go to |
|---|---|
| REST API / web / webhooks routes | `routes/api.php`, `routes/web.php`, `routes/webhook.php`, `routes/platform.php` (Orchid) |
| Scheduler (all crons) | `app/Console/Handler.php` (wired in `bootstrap/app.php`) |
| Middleware aliases | `app/Http/Middleware/Handler.php` (`api.key`, `api.key.readonly`, `api.access_ip`) |
| Wallet generation, send, routing | `app/Services/Wallet/WalletService.php`, `app/Services/Tron/TronService.php` |
| Key sealing | `app/Services/Wallet/KeyManager.php`, `enc/` (see `enc/README.md`) |
| cleanroom-watcher client / webhook | `app/Services/Wallet/WalletListenerService.php`, `app/Http/Controllers/WalletWebhookController.php` |
| AML | `app/Services/Aml/` (`AmlServiceDispatcher`, `AmlChecksManager`, `AmlColorResolver`, `Services/*`, `Export/`), jobs `app/Jobs/Aml/<Provider>/` |
| AI verdict | `app/Services/Ai/`, `app/Jobs/Ai/AiAmlCheckJob.php`, prompt `docs/aml-prompt.md`, report `app/Reports/AiAml/` |
| Energy | `app/Services/Energy/`, `app/Jobs/Wallet/AddEnergy.php`, `app/Jobs/Energy/SmartEnergyJob.php`, `app/Models/Energy/` |
| Chains | `app/Services/Blockchain/` (`Services/BlockchainServiceRegistry.php`, base classes, `EVM/`) |
| TRON nodes | `app/Services/Tron/TronNodeDispatcher.php`, `app/Models/Tron/TronNode.php` |
| Telegram bots | controllers `app/Http/Controllers/Telegram/`, logic `app/Services/Telegram/{MainBotService,OperationsBotService}.php`, texts `resources/lang/en/` |
| Recipients | `app/Models/Recipient/`, `app/Repositories/RecipientRepository.php`, `app/Repositories/Recipient/`, `app/Orchid/Screens/Recipients/` |
| Ledger integration (optional) | `app/Services/Ledger/LedgerService.php`, `app/Models/Recipient/LedgerAccount*.php`, `LedgerAccountController`, `app/Listeners/Ledger/`, `RecipientAccountsScreen`; see `docs/LEDGER_INTEGRATION.md` |
| Enums (state machines) | `app/Enums/` (`WalletStatuses`, `TransactionStatuses`, `AmlAddressStatuses`, `WalletOperationsTypes`) |
| Admin panel | `app/Orchid/{Screens,Layouts,Filters}`, menu `app/Orchid/PlatformProvider.php` |
| Alerts / ops commands | `app/Console/Commands/{Alerts,Wallets,Transactions,Aml,Tron,Telegram}` |
| Schema / seeds | `database/migrations`, `database/seeders` |
| Docker | `docker/` (local), `docker/prod/` (production, own README) |

## Key abstractions
**Dispatchers.** One pattern for every external provider: an interface, a list of implementations built in the dispatcher constructor, and a DB table (`code`, `is_enabled`, `priority`, keys) filled by a seeder and editable in Orchid.
- `AmlServiceDispatcher` — `AmlServiceInterface` (AmlBot, WellWallet, GetBlock, BitOK, CoinKYT). Runs all enabled providers that `supportsBlockchain()`; each provider has address and transaction jobs in `app/Jobs/Aml/<Provider>/`.
- `EnergyServiceDispatcher` — `EnergyServiceInterface` (Refee, TrEnergy, Netts). Picks the first enabled by `priority`; `rentEnergy`, `getCost`, `getBalance`, `smartEnergy` (auto-replenishment).
- `AiAmlServiceDispatcher` — `AiAmlServiceInterface` (`OpenAiService`). Active record in `ai_services` by `is_enabled` + `priority`; prompt, model and API key come from that record (fallback to env).

**Registries.** `BlockchainServiceRegistry` maps `AmlAddress::BLOCKCHAIN_TYPE_*` → `BlockchainServiceInterface` (balance, tx count, last hash, account info) for 14 chains; implementations extend `BlockchairBasedService`, `BlockCypherBasedService`, `EtherscanV2BasedService` or `SoChainBasedService`.

**Repositories.** `app/Repositories/*` extend `Repository`; all queries and updates of wallets, transactions, AML, recipients and energy wallets go through them. Controllers and services do not build queries inline.

**Wallet status state machine** (`WalletStatuses`):
`NEW → ADD_TRANSACTION → CHECK_TRANSACTION_START → CHECK_TRANSACTION_FINISH → TRANSFER_MONEY_PROCESS_START → TRANSFER_MONEY_PROCESS_FINISH → COMPLETED`, plus `TIME_OUT` for unpaid wallets. `TransactionStatuses`: `NEW → PROCESS → FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY | FUNDS_VERIFICATION_FAILED | AML_ERROR`. Labels: `Wallet::getStatuses()` / `getStatusesEn()`. Every wallet change is logged in `wallets_logs` via `WalletLoggerService`.

**AML aggregation** (`AmlChecksManager`). On start it creates a pending row per provider in `transactions_aml_checks` / `aml_addresses_checks`; each job marks its row completed, skipped or failed; finalisation runs only when no pending rows remain (manual `forceComplete*()` exists). Finalisation writes `aml_risk_score` / report link (AmlBot first, else first completed provider) and moves the wallet to `CHECK_TRANSACTION_FINISH`. Full per-provider data is exposed as `amls` in API resources. `AmlColorResolver::forCheck()` computes a colour per provider for comparison with the AI verdict; its thresholds mirror `docs/aml-prompt.md` — change both together.

**Colour routing.** Recipient destination wallet types: `TYPE_WHITE=1`, `TYPE_GRAY=2`, `TYPE_BLACK=3`, `TYPE_YELLOW=4` (`App\Models\Recipient\Wallet`). AI colours `WHITE/YELLOW/GREY/BLACK` are stored in `wallet_ai_aml_checks`. `WalletService::send($wallet, $type, $toAddress, $amount)` resolves the recipient wallet of that type, sets `address_to`, `TRANSFER_MONEY_PROCESS_START`, dispatches `SendUsdt` (which calls `AddEnergy` first). Triggers: `POST /api/wallet/send/{address}/{type}`, Orchid `WalletInfoScreen`, operations bot. `setWhite/Gray/YellowWallet()` only pre-set `address_to` (+ `type`). Payout rule (`WalletService::isAllowedDestination`, re-checked in `SendUsdt`/`SendTrx`): `buy` WHITE/GRAY/YELLOW → only a wallet of the same recipient and colour; only BLACK may use a free address; `sell` unrestricted.

**Buy / sell** (`WalletOperationsTypes`). `buy` (default): AML then routing. `sell`: `address_to` is required at generation, AML is skipped, `WalletService::autoSendForSell()` sends when funds arrive and `Setting::isAutoSendSellEnabled()`.

**KeyManager + encryptor.** `KeyManager` runs `enc/encryptor` via Symfony `Process` (`encrypt <connect> <key>`, `decrypt <connect> <part> <secret> <salt>`, `encrypt_bd`). The encryptor keeps half of the per-record AES key in its vault DB and returns the other half (`secret`) plus `salt`; the app stores those with the wallet. `connect` selects the vault DSN from `enc/config.json` (`Wallet::CRYPTO_DEFAULT_CONNECT`). Optional encrypted copy (`encrypt_bd`) to `backup_db` via `BackupWalletKeyJob` when `WALLET_KEY_BACKUP_ENABLED`. Smoke test: `php artisan test-encryptor:run`.

**TronNodeDispatcher** (singleton). Nodes live in `tron_nodes` (url, `api_key`, `priority`, `is_enabled` set by hand, `is_available` set by alerts). Active node = first by `priority` among enabled + available; TronGrid is last as fallback. `AlertNodeDeSyncBlocks` (lag > 50 blocks vs TronGrid, every 10 min) and `AlertNodeStopBlocks` (no new block, every 3 min) flip `is_available`; recovered nodes return automatically. `TRON_*` env values only seed `TronNodesSeeder`. Manage in Orchid → Settings → TRON nodes, operations bot → Settings, `php artisan test:nodes [--check]`.

**Telegram bots.** Two bots, webhook delivery (`/telegram/webhook`, `/telegram/operations/webhook`, secret-token middlewares), `telegram:poll` / `operations-telegram:poll` for local dev. `BaseTelegramController` deduplicates updates in Redis and checks access: recipient bot → user exists in `tg_users`; operations bot → id in `Setting::getOperationsBotTelegramUsers()`. Dialog state is a Redis key `telegram_state:{chatId}` (TTL 1 h) set by `saveState()`; handlers branch on state and `callback_data`. Recipient bot: recipients, destination wallets, energy wallets, reports, search. Operations bot: find a payment, set colour / send, request energy, energy services, TRON nodes, settings. Outgoing messages go through `TelegramSendJob` / `TelegramHelper`.

**Recipient model.**
| Table | Purpose |
|---|---|
| `recipients` | `external_id` (unique, public API id, UUID if empty), `name`, `comment`, soft delete |
| `recipient_wallets` | destination wallets: `recipient_id`, `type` (colour), `blockchain_type`, `address` (unique), soft delete; one wallet per type |
| `recipient_wallets_log` | change journal of destination wallets |
| `tg_users` | Telegram users, flag `can_create_recipients` |
| `recipient_tg_user` | M:N users ↔ recipients (who sees the recipient in the bot) |
| `ledger_accounts`, `ledger_account_wallets` | optional ledger mirror: accounts per recipient (`ledger_id`, `wallet_id` → `recipient_wallets`, `is_show_bot`) and linked addresses (`ledger_wallet_id`, `network`); `recipients.ledger_user_id`, flag `tg_users.can_manage_ledger_accounts` |

**Ledger integration (optional).** Off unless `LEDGER_API_URL` + `LEDGER_API_TOKEN` are set (`LedgerService::isConfigured()`): Orchid tab/field/flag hidden, bot button and `ledger_*` callbacks ignored, `/api/ledger-accounts/*` → 503, `SyncLedgerWalletOnAddressChange` no-op. Recipient wallets are not registered in cleanroom-watcher, so ledger balances need that elsewhere.

FKs to recipients: `wallets.recipient_id`, `wallets.recipient_wallet_id`, `transactions.recipient_id`, `energy_wallets.recipient_id / recipient_wallet_id`. API param `recipient_id` = `recipients.external_id`. Orchid permission `platform.recipients.list`.

## Wallet lifecycle (buy)
1. `POST /api/wallet/trc-20/generate` → `WalletController::generate` → `WalletService::generateWallet` → `TronService::generateWallet` (key pair, `KeyManager::encrypt`, status `NEW`).
2. `SendWalletToListenerService` registers the address in cleanroom-watcher.
3. `POST /webhook/wallet-listener` (HMAC `X-Signature` checked first) → `WalletRepository::syncTransactionWebHook` → transaction row, `ADD_TRANSACTION`, `ActivateWallet`, `runAmlTransaction`. Crons `sync-new:transactions:run` / `sync-finish:transactions:run` cover missed webhooks.
4. `AmlServiceDispatcher::dispatchTransactionChecks` → provider jobs → `AmlChecksManager` finalises → `CHECK_TRANSACTION_FINISH` (+ `AiAmlCheckJob` when `Setting::AI_AML_CHECK_ENABLED`).
5. Colour decision → `WalletService::send` → `SendUsdt` → `AddEnergy` (rent) → transfer → `TRANSFER_MONEY_PROCESS_FINISH` → `COMPLETED` (`set-completed-transactions:run`).
6. `wallets-set-timeout:run` moves unpaid wallets to `TIME_OUT`.

## Recipes
**Add an AML provider.** (1) Class in `app/Services/Aml/Services/` implementing `AmlServiceInterface`. (2) Jobs `app/Jobs/Aml/<Provider>/{AmlAddressCheck,AmlTransactionCheck}.php` that end with `AmlChecksManager::checkAndFinalize*()`. (3) Add to `AmlServiceDispatcher::__construct`. (4) Row in `AmlServicesSeeder` + `AmlService::*_CODE`. (5) Keys in `config/services.php` + env name. (6) Optional balance alert in `app/Console/Commands/Alerts/Aml/` and schedule in `app/Console/Handler.php`.

**Add an energy provider.** Implement `EnergyServiceInterface` in `app/Services/Energy/Services/`, add to `EnergyServiceDispatcher::__construct`, add a row in `EnergyServicesSeeder`, keys in `config/services.php`.

**Add an AI service.** Implement `AiAmlServiceInterface` in `app/Services/Ai/Services/`, register in `AiAmlServiceDispatcher`, add to `AiServicesSeeder`.

**Add a chain for lookups.** Class in `app/Services/Blockchain/` (or `EVM/`) extending a base class, constant `AmlAddress::BLOCKCHAIN_TYPE_*`, register in `BlockchainServiceRegistry`. For wallet generation also add a branch in `WalletService::generateWallet` and a type in `Recipient\Wallet::blockChainTypesNames()` with its regexp.

**Add a wallet status.** Case in `WalletStatuses`, labels in `Wallet::getStatuses()` / `getStatusesEn()`, bot texts in `resources/lang/en/`, API doc table.

**Add a bot screen.** Button + `callback_data` in `MainBotService` / `OperationsBotService`, handler branch, state via `saveState()` for text input, texts in `resources/lang`.

## Commands
```bash
cd docker && make up | make migrate | make seed | make tests | make go-php   # local stack
php artisan test                          # or ./vendor/bin/phpunit --filter Name (DB_CONNECTION=sqlite)
php artisan queue:work                    # jobs (AML, energy, sends, Telegram)
php artisan schedule:work                 # crons from app/Console/Handler.php
php artisan set-wh-telegram:run           # webhooks for both bots; wh-info-telegram:run to inspect
php artisan test:energy [--balance] [--service=all] [--cost --address=T...]
php artisan test:nodes [--check]
php artisan test-encryptor:run
php artisan test:ledger --health           # optional ledger: --groups, --accounts --user-id=, --set-wallet
php artisan aml:export                    # export AML address database
```
Composer runs on PHP 8.4 without platform flags (`make bootstrap`, see `docs/QUICKSTART.md`). Logs: channels `tron`, `tronscan`, `aml`, `getblock`, `energy`, `security`, `telegram`, `wallet_webhook`, `requests`, `ledger` in `storage/logs/`; web UI `/log-viewer`.

## Environment variables (names only)
- **App:** `APP_URL`, `APP_KEY`, `APP_TIMEZONE`, `DASHBOARD_PREFIX`, `GLOBAL_OUR_API_KEY`, `GLOBAL_READONLY_API_KEY`
- **DB / Redis:** `DB_*`, `BACKUP_DB_*`, `WALLET_KEY_BACKUP_ENABLED`, `REDIS_*`, `QUEUE_CONNECTION`
- **TRON:** `TRON_FULL_NODE`, `TRON_SOLIDITY_NODE`, `TRON_EVENT_SERVER`, `TRON_API_KEY`, `TRON_2_*`, `TRONGRID_*`
- **Listener:** `WALLET_LISTENER_SERVICE_URL`, `WALLET_LISTENER_WEBHOOK_SECRET` (API key, `X-API-Key`), `WALLET_LISTENER_HMAC_SECRET` (webhook `X-Signature`, required, fail closed)
- **AML:** `AML_ACCESS_ID`, `AML_ACCESS_KEY`, `WELL_WALLET_API_KEY`, `WELL_WALLET_BASE_URL`, `AML_GETBLOCK_TOKEN`, `AML_BITOK_KEY_ID`, `AML_BITOK_SECRET_KEY`, `AML_COINKYT_*`, `AML_EXPORT_SOURCE`
- **AI:** `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_ADMIN_API_KEY`, `ANTHROPIC_API_KEY`, `CLAUDE_MODEL`
- **Energy:** `REFEE_TOKEN`, `TR_ENERGY_API_KEY`, `NETTS_API_KEY`
- **Explorers:** `ETHERSCAN_API_KEY`, `BSCSCAN_API_KEY`, `POLYGONSCAN_API_KEY`, `ARBISCAN_API_KEY`, `OPTIMISM_ETHERSCAN_API_KEY`, `BASESCAN_API_KEY`, `SNOWTRACE_API_KEY`, `BLOCKCYPHER_TOKEN`
- **Ledger (optional):** `LEDGER_API_URL`, `LEDGER_API_TOKEN`
- **Telegram:** `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_WEBHOOK_URL`, `TELEGRAM_SECRET_TOKEN`, `TELEGRAM_CERTIFICATE_PATH`, `TELEGRAM_OPERATIONS_{BOT_TOKEN,CHAT_ID,WEBHOOK_URL,SECRET_TOKEN}`
- **Docker:** `ENVIRONMENT`, `DB_ROOT_PASSWORD`, `DB_*_KEY_STORAGE`, `REDIS_PASSWORD`

## Conventions
- Laravel defaults, PSR-12, typed properties and return types; enums in `app/Enums`, constants on models for small type sets.
- Data access through repositories; business logic in services; long or external work in queued jobs (`ShouldBeUnique` where double-run costs money).
- New external provider = interface + dispatcher + DB row + seeder, never a direct call from a controller.
- Bot and Orchid texts are short, operator language; technical detail goes to logs and `docs/` (see `rules.md`).
- Every wallet change that matters is logged through `WalletLoggerService`; provider calls log to their channel.
- Comments, identifiers, log keys and error codes are in English.
- No secrets or real hosts in code or docs; use `example.com` and env names.
