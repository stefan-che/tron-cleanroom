# Ledger integration (optional)

The ledger is an external account-bookkeeping service (a Telegram bot with its own HTTP API). The
integration lets you link a recipient's wallets to their ledger accounts from the admin panel, from the
recipients' bot and through the API. Processing works fully without it; it is disabled by default.

"Ledger" here means **Tally** ([tally-bot](https://github.com/stefan-che/tally-bot)), the companion bookkeeping bot; it is
unrelated to Ledger hardware wallets. The `LEDGER_*` variables and `Ledger*` class names are kept as identifiers.

## Enabling

1. In `.env`: `LEDGER_API_URL` (for example `http://tally-bot:8080`, port = the ledger's `API_PORT`) and
   `LEDGER_API_TOKEN` (= the ledger's `API_TOKEN`, sent in the `X-API-KEY` header). Config: `config/services.php` → `ledger`.
2. Recipient → "Recipient data" → **Ledger ID** (`recipients.ledger_user_id`) — the user ID in the ledger,
   used to fetch accounts (`GET /api/accounts?user_id=`).
3. Recipient → **"Ledger accounts"** tab → "Refresh accounts from ledger"; mark accounts as "In bot".
4. For the Telegram user who will link wallets in the bot, enable **"Ledger accounts in bot"**
   (`tg_users.can_manage_ledger_accounts`).
5. Check: `php artisan test:ledger --health`, `--accounts --user-id=...`.

With empty `LEDGER_API_URL` / `LEDGER_API_TOKEN`: the tab, field and flag in the admin panel are hidden, there is no
button in the bot, the `/api/ledger-accounts/*` API responds `503`, and the address-change listener does nothing.

## How it works

| Where | What |
|-------|------|
| `App\Services\Ledger\LedgerService` | HTTP client: `health`, `getGroups`, `getAccounts`, `updateAccountWallet`, `deleteAccountWallet`; when not configured it returns `503` without making a request |
| `ledger_accounts` (`LedgerAccount`) | mirror of the recipient's accounts: `ledger_id`, `group_id`, `recipient_id`, `user_id`, `name`, `currency`, `amount`, `wallet_id` (the recipient's wallet), `is_show_bot` |
| `ledger_account_wallets` (`LedgerAccountWallet`) | linked addresses: `ledger_account_id`, `ledger_wallet_id` (UUID in the ledger), `wallet_address`, `network` |
| Orchid `RecipientAccountsScreen` | account sync by `ledger_user_id`, show-in-bot toggle, list of an account's wallets |
| Recipients' bot | "Edit wallet" → "🗂 Manage accounts" → pick an account → link/unlink (`ledger_*` callbacks) |
| API | `POST /api/ledger-accounts/{ledgerId}/wallet`, `DELETE /api/ledger-accounts/{ledgerId}/wallet/{ledgerWalletId}` — see [API_REFERENCE.md](API_REFERENCE.md#ledger-accounts--ledgeraccountcontroller) |
| `SyncLedgerWalletOnAddressChange` | on `RecipientWalletAddressChanged` sends the new address to every account where `wallet_id` = this wallet |
| Logs | channel `ledger` → `storage/logs/ledger.log` |

`GET /api/recipients*` additionally returns `ledger_accounts` (only accounts marked "In bot") if the
recipient has `ledger_user_id` set.

## Ledger API contract (what processing calls)

All requests except `/health` carry the `X-API-KEY` header. The ledger may restrict IPs (`ALLOWED_IPS`).

| Method | Path | Parameters / body | Response |
|--------|------|-------------------|----------|
| GET | `/health` | — | `{"status":"ok"}` |
| GET | `/api/groups` | `page`, `per_page` (1–100), `tg_id` | `{data:[{id,tg_id,title,status,chat_type}], pagination:{page,per_page,total,total_pages}}` |
| GET | `/api/accounts` | `page`, `per_page`, `user_id`, `group_id` | `{data:[{id,group_id,user_id,name,currency,amount,decimal,wallets:[{id,wallet_address,network,balance}]}], pagination}` |
| POST | `/api/accounts/{id}/wallets` | `{"wallet_address":"T...","network":"tron"}` | `201` new / `200` already existed: `{id,account_id,wallet_address,network}` |
| DELETE | `/api/accounts/{id}/wallets/{wallet_id}` | — | `204` |

Ledger errors: `400` (validation), `401` (key), `403` (IP), `404` (no account/wallet), `500`;
body `{"error":"..."}`. Processing passes the status and `error` to the API client as is.

## Limitations (unchanged from the original integration)

- **Account balances in the ledger do not appear on their own.** The ledger counts incoming funds for the
  addresses tracked by cleanroom-watcher, while processing registers only transit wallets for tracking
  (`SendWalletToListenerService`), not recipient wallets. Who registers a recipient address in
  cleanroom-watcher is an open question; for now this is done outside processing.
- Unlinking in the bot removes the first address linked to the account; if there is no local record,
  `wallet_address: null` is sent, which the current ledger API rejects (`400`).
- `amount` is stored as an integer (`(int)`); the fractional part of the ledger balance is dropped.
