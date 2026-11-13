# Architecture

How the four repositories of the stack fit together: who does what, where keys live, and the exact contracts between them. Overview: [README.md](README.md). Security policy: [SECURITY.md](SECURITY.md). Setup: [docs/QUICKSTART.md](docs/QUICKSTART.md).

Contracts below are read from the code **as of 2026-11-07**. When a component repository changes a contract, its own docs win; update this file in the same release.

## 1. Components and responsibilities

| Component | Owns | Does not do |
|---|---|---|
| **tron-cleanroom** (this repo, core) | Transit wallet generation (USDT TRC-20 / TRX), the wallet lifecycle, AML screening across providers, risk tiers, recipients and their destination wallets, sweeps with rented TRON energy, admin panel (Orchid), recipient bot and operations bot, monitoring alerts | Scanning the chain itself; storing private keys in plain form; calling your backend back (your backend polls) |
| **[cleanroom-watcher](https://github.com/stefan-che/cleanroom-watcher)** (required) | Block-by-block scan of TRON for TRX and one USDT contract, the set of watched addresses with TTL, signed webhook delivery with a persisted delivery log and retries, transaction history per watched address | Any decision about the money; key handling |
| **[cleanroom-vault](https://github.com/stefan-che/cleanroom-vault)** (required) | Sealing and opening private keys with a per-record split AES key, the vault database, master-key and KEK rotation | Network access (CLI only, one process per operation) |
| **[tally-bot](https://github.com/stefan-che/tally-bot)** (optional) | Per-chat bookkeeping ledger in Telegram; automatic accounts whose balance is the sum of transfers of their bound wallets | Registering watches in cleanroom-watcher; any role in the payment flow |

## 2. Trust boundaries and where keys live

```
 ┌──────────────── your backend ────────────────┐
 │ X-API-KEY (+ IP allow-list) to tron-cleanroom │
 └───────────────────────┬──────────────────────┘
                         │ REST
 ┌───────────────────────▼──────────────────────┐      subprocess, argv / stdout JSON
 │ tron-cleanroom   (app DB: key part + secret + │ ───────────────────────────────┐
 │                   salt + hash per wallet)     │                                │
 └──────▲─────────────────────┬─────────────────┘                    ┌───────────▼───────────┐
        │ webhook, X-Signature │ POST /api/watch, X-API-Key           │ cleanroom-vault        │
        │ (HMAC-SHA256)        ▼                                      │ config.json + kek file │
 ┌──────┴───────────────────────────────────────┐                    │ vault MySQL: half key  │
 │ cleanroom-watcher (MySQL, Redis optional)     │                    └────────────────────────┘
 └──────▲───────────────────────────────────────┘
        │ GET /api/addresses/{addr}/transactions, X-API-Key
 ┌──────┴──────────┐
 │ tally-bot       │ (optional; Postgres)
 └─────────────────┘
```

**Where a private key lives.** Nowhere in plain form at rest. For each wallet cleanroom-vault generates a random 256-bit AES key and splits it into two 16-byte halves. Half 1 goes to the vault database (`wallet_keys`, append-only user: SELECT, INSERT). Half 2 (`secret`) and a sealed lookup link (`salt`) are returned to tron-cleanroom and stored with the wallet together with the ciphertext (`key`) and a `hash`. The master key that seals the link is stored wrapped by a KEK, a file beside the encryptor binary. To open a key, the operator uses the app record, the vault row, `config.json` and the `kek` file. A key is decrypted just in time for a sweep. Store the application database and the vault — and their backups — on separate hosts with separate credentials (see the [cleanroom-vault README](https://github.com/stefan-che/cleanroom-vault)).

**Trust boundaries.**
- Your backend → tron-cleanroom: API key and IP allow-list; set both before exposing the API.
- cleanroom-watcher → tron-cleanroom: HMAC over the raw body; requests are rejected while the secret is not set.
- tron-cleanroom → cleanroom-watcher, tally-bot → cleanroom-watcher: `X-API-Key`, checked against `api.api_keys` in cleanroom-watcher.
- tron-cleanroom → cleanroom-vault: local process call. Restrict who can run the binary and read its `config.json` and `kek` to the application user.
- Admin panels (tron-cleanroom, cleanroom-watcher, tally-bot) and the Telegram bots: separate logins; see [SECURITY.md](SECURITY.md).

## 3. One payment, step by step (buy flow)

1. Your backend calls `POST /api/wallet/trc-20/generate` on tron-cleanroom with a `recipient_id` and your `external_id`.
2. tron-cleanroom creates a TRON key pair and calls cleanroom-vault `encrypt`. It stores `key`, `salt`, `secret`, `hash` and the connection id; the plain key is discarded.
3. tron-cleanroom puts the new address on watch: `POST /api/watch` on cleanroom-watcher (TTL 168 h by default).
4. The payer sends USDT TRC-20 to the transit address.
5. cleanroom-watcher sees the transfer after the configured confirmations, stores it, and POSTs a signed webhook to tron-cleanroom. A cron in tron-cleanroom also catches transfers the webhook missed.
6. If the amount is above the AML threshold (default 101 USDT), tron-cleanroom screens the source with the enabled AML providers, saves the results and PDF reports, and sets the risk tier. The optional AI review (OpenAI, off by default) adds an advisory tier.
7. Your backend polls `GET /api/wallet/status/{address}`.
8. The sweep is started by your backend (`POST /api/wallet/send/{address}/{type}`), by an operator in the admin panel, or in the operations bot. tron-cleanroom rents energy, decrypts the key through cleanroom-vault and sends the funds.
9. Payout guard: WHITE, YELLOW and GREY may go only to the recipient's wallet of the same tier. Only BLACK may go to an address you specify as `to-address`.

Sell flow: the destination is fixed at creation, AML is skipped, and the funds are sent automatically when they arrive if that setting is on.

## 4. Cross-repo contracts (as of 2026-11-07)

### 4.1 tron-cleanroom → cleanroom-watcher: put an address on watch
- `POST {WALLET_LISTENER_SERVICE_URL}/api/watch`
- Header `X-API-Key: <WALLET_LISTENER_WEBHOOK_SECRET>`. Despite the env name, the value is a cleanroom-watcher API key.
- Body:
  ```json
  {"address": "T...", "network": "tron", "ttl_hours": 168,
   "webhooks": [{"url": "<route webhook.wallet-listener>", "secret": "<WALLET_LISTENER_HMAC_SECRET>",
                 "timeout_sec": 10, "max_retries": 3}],
   "meta": {"label": "...", "id": "..."}}
  ```
- cleanroom-watcher's `max_retries` counts total attempts (3 = three attempts), retry delays 5 s / 30 s / 60 s.

### 4.2 cleanroom-watcher → tron-cleanroom: incoming transfer webhook
- `POST /webhook/wallet-listener` on tron-cleanroom (`http://tron-cleanroom/webhook/wallet-listener` on the shared Docker network), `Content-Type: application/json`.
- Header `X-Signature: sha256=<hex HMAC-SHA256 of the raw body>`, key = `WALLET_LISTENER_HMAC_SECRET`. Checked in constant time before parsing; empty secret → 401.
- Body fields: `network, currency, txid, block, ts, address, from, to, amount, amount_raw, fee, fee_currency, direction`. Amounts are decimal strings. tron-cleanroom keys on `address`.
- The signature covers the raw body. Deduplicate by `txid` on the receiver.

### 4.3 tron-cleanroom → cleanroom-vault: seal and open keys
- Local CLI `enc/encryptor`, run from its directory (reads `config.json` from the working directory, `kek` from the binary's directory). Details: [enc/README.md](enc/README.md).
- `encrypt <connect> <plaintext>` → stdout JSON `{"key", "salt", "secret", "hash"}`.
- `decrypt <connect> <key> <secret> <salt>` → stdout JSON `{"result"}`. The caller compares `hash` after decrypt.
- `encrypt_bd` writes an optional encrypted copy to a backup DB (`WALLET_KEY_BACKUP_ENABLED`).
- Default connection `3`. On error: non-zero exit and `Error code N` on stderr. The wire format is frozen.
- The vault schema is [cleanroom-vault/db.sql](https://github.com/stefan-che/cleanroom-vault/blob/main/db.sql); the Docker setup of tron-cleanroom mounts it into the vault container.

### 4.4 tally-bot → cleanroom-watcher: read wallet transactions
- `GET {LISTENER_API_URL}/api/addresses/{addr}/transactions?network=&per_page=&page=&sort=`
- Header `X-API-Key: <LISTENER_API_KEY>`. Fields read: `txid, amount, direction, ts, currency`.
- 404 means the address is not watched; tally-bot skips it. Polled every 5 minutes (`WALLET_SYNC_INTERVAL`).

### 4.5 tron-cleanroom → tally-bot (optional)
- Off unless `LEDGER_API_URL` and `LEDGER_API_TOKEN` are set; otherwise `/api/ledger-accounts/*` on tron-cleanroom answers 503. Details: [docs/LEDGER_INTEGRATION.md](docs/LEDGER_INTEGRATION.md).
- Endpoints: `GET /health`, `GET /api/groups`, `GET /api/accounts`, `POST /api/accounts/{id}/wallets`, `DELETE /api/accounts/{id}/wallets/{wid}`.
- Header `X-API-KEY: <LEDGER_API_TOKEN>`. tally-bot refuses API requests when its token is empty.

### 4.6 Shared network
In Docker, the services meet on the network `processing-net`: `http://cleanroom-watcher:8080`, `http://tron-cleanroom` (nginx alias).

## 5. Operational note

tron-cleanroom puts transit wallets on watch. To see tally-bot balances for a recipient's destination wallets, register those addresses in cleanroom-watcher with `POST /api/watch`; tally-bot reads watched addresses and does not register them itself.
