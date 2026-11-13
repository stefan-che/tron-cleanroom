# API Reference

Reference for all HTTP endpoints of the application. Sources: `routes/api.php`, `routes/web.php`,
`routes/webhook.php` and the controllers in `app/Http/Controllers`.

Message strings returned by the API (validation and business errors, status names) are in English.

## Contents

- [General rules](#general-rules)
- [Public API (`/api/*`)](#public-api-api)
  - [Wallets — `WalletController`](#wallets--walletcontroller)
  - [AML addresses — `AmlController`](#aml-addresses--amlcontroller)
  - [Transactions — `TransactionController`](#transactions--transactioncontroller)
  - [Recipients — `RecipientController`](#recipients--recipientcontroller)
  - [Energy wallets — `EnergyWalletController`](#energy-wallets--energywalletcontroller)
  - [Ledger accounts — `LedgerAccountController`](#ledger-accounts--ledgeraccountcontroller) (optional)
- [Web endpoints (reports)](#web-endpoints-reports)
- [Webhooks (inbound)](#webhooks-inbound)
- [Utility endpoints](#utility-endpoints)
- [Value reference](#value-reference)

---

## General rules

### Base URL

The application domain from `APP_URL`. All endpoints in the "Public API" section are prefixed with `/api`.

### Authentication

All routes in `routes/api.php` are protected by two middleware (`app/Http/Middleware/Handler.php`):

| Middleware       | Class                     | What it checks |
|------------------|---------------------------|----------------|
| `api.key`        | `ApiKeyMiddleware`        | The `X-API-KEY` header must match one of the values in `GLOBAL_OUR_API_KEY` (comma-separated list) |
| `api.access_ip`  | `AccessApiIpMiddleware`   | The client IP must be listed in the `access_api_ip` setting (`settings` table, separators `,` or `\|`) |

```
X-API-KEY: <key>
Accept: application/json
```

Notes:

- If `GLOBAL_OUR_API_KEY` is empty, the key check is **disabled** (all requests pass).
- If the `access_api_ip` setting is empty, the IP check is **disabled**.
- The IP is taken from `HTTP_CF_CONNECTING_IP` (Cloudflare), otherwise `request()->ip()`.
- Failed attempts are written to the `security` log channel.

On rejection both checks respond identically:

```http
HTTP/1.1 403 Forbidden
```
```json
{ "message": "Forbidden" }
```

### Response format

A **single resource** is returned without a wrapper (`$wrap = ''` in the resources):

```json
{ "id": 1, "address": "TR...", "...": "..." }
```

A **collection** is wrapped in `data`. For paginated lists Laravel adds `links` and `meta`:

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "from": 1, "last_page": 3, "path": "...", "per_page": 30, "to": 30, "total": 84 }
}
```

### Error format

A **validation error** (`ApiRequest::failedValidation`) returns code `400`:

```json
{
  "errors": {
    "external_id": ["A wallet for this external_id has already been generated."]
  },
  "success": false
}
```

A **business error** (`Controller::returnError`) returns code `404` by default:

```json
{
  "success": false,
  "message": "Could not find such a wallet"
}
```

**Status code summary**

| Code | When |
|------|------|
| 200 | Success |
| 202 | `POST /api/aml/{blockchain}/{address}` only — the check could not be started |
| 400 | Input validation error |
| 403 | Invalid `X-API-KEY` or IP outside the allowlist |
| 404 | Entity not found |
| 422 | Operation failed (could not create a wallet / send funds / activate) |
| 500 | Internal error |

### URL parameters

`ApiRequest::all()` merges route parameters into the validated data, so `blockchain`, `address`,
`type` and similar URL parameters are validated the same way as the request body and are reported
in errors under the same names.

---

## Public API (`/api/*`)

Summary table:

| Method | Path | Route name | Controller method |
|--------|------|------------|-------------------|
| GET | `/api/wallet/list` | `api.wallet.list` | `WalletController@getList` |
| POST | `/api/wallet/{blockchain}/generate` | `api.wallet.generate` | `WalletController@generate` |
| GET | `/api/wallet/status/{address}` | `api.wallet.status` | `WalletController@status` |
| POST | `/api/wallet/send/{address}/{type}` | `api.wallet.send` | `WalletController@send` |
| POST | `/api/wallet/activate/{address}` | `api.wallet.activate` | `WalletController@activate` |
| POST | `/api/wallet/address-to/{address}` | `api.wallet.address-to` | `WalletController@changeAddressTo` |
| POST | `/api/aml/{blockchain}/{address}` | `api.aml.check` | `AmlController@check` |
| GET | `/api/aml/status/{blockchain}/{address}` | `api.aml.blockchain_status` | `AmlController@status` |
| GET | `/api/aml/status/{address}` | `api.aml.status` | `AmlController@status` |
| POST | `/api/transaction/{hash}/run-aml` | `api.transaction.runaml` | `TransactionController@runAml` |
| GET | `/api/recipients` | `api.recipients.index` | `RecipientController@index` |
| GET | `/api/recipients/{identifier}` | `api.recipients.show` | `RecipientController@show` |
| GET | `/api/energy-wallets` | `api.energy-wallets.index` | `EnergyWalletController@index` |
| GET | `/api/energy-wallets/{id}` | `api.energy-wallets.show` | `EnergyWalletController@show` |
| POST | `/api/ledger-accounts/{ledgerId}/wallet` | `api.ledger-accounts.wallet.link` | `LedgerAccountController@linkWallet` |
| DELETE | `/api/ledger-accounts/{ledgerId}/wallet/{ledgerWalletId}` | `api.ledger-accounts.wallet.unlink` | `LedgerAccountController@unlinkWallet` |

---

## Wallets — `WalletController`

File: `app/Http/Controllers/WalletController.php`

### `Wallet` object (`WalletResource`)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Wallet ID |
| `address` | string | Wallet address on the blockchain |
| `info` | object | Token: `{"name":"Tether Token","symbol":"USDT","decimals":6}` |
| `type_name` | string | Destination type: `white`/`gray`/`yellow`/`black`, or `----` |
| `operation_type` | int | `1` — receive (buy), `2` — payout (sell) |
| `operation_type_name` | string | `buy` / `sell` |
| `status` | int | Status code, see [wallet statuses](#wallet-statuses) |
| `status_name` | string | Status label |
| `status_name_en` | string | Machine-readable status (`new`, `completed`, …) |
| `amount` | float\|null | Current wallet balance (USDT) |
| `send_tx_hash` | string\|null | Hash of the outgoing transaction |
| `address_to` | string\|null | Address the funds are sent to |
| `amls` | object | AML results for the latest transaction, keyed by service code (see [AML check object](#aml-check-object)) |
| `aml_risk_score` | float\|null | Risk score **from AmlBot only** (backward compatibility) |
| `aml_file_report_link` | string | Report link (`/wallet/report/{address}`), or `""` |
| `comment` | string\|null | Comment |
| `has_error` | bool | Error flag |
| `is_test` | bool | Test wallet |
| `last_transfer_amount` | float\|null | Amount of the last transfer |
| `ai_check` | object\|null | AI AML check result, or `null` |
| `transactions` | array | List of transactions (`TransactionResource`) |

`ai_check` object:

| Field | Type | Description |
|-------|------|-------------|
| `text` | string | AI conclusion text (HTML stripped) |
| `wallet_color` | string | `WHITE` / `YELLOW` / `GREY` / `BLACK` |
| `risk` | string | Risk level (`LOW`/`MEDIUM`/`HIGH`) |
| `confidence` | int | Confidence, % |
| `service` | string\|null | AI service name |
| `checked_at` | string | ISO-8601 |
| `coinkyt` | object\|null | Separate verdict based on CoinKYT data only: `text`, `wallet_color`, `risk`, `confidence` |

> For `is_test = true` and in the local environment, `ai_check` returns demo data.

---

### `POST /api/wallet/{blockchain}/generate`

Generates a new wallet and queues it for listening (`SendWalletToListenerService`).

**Path parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `blockchain` | string | `trc-20`, `btc`, `eth` (`Recipient\Wallet::blockChainTypesNames()`) |

**Request body**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `external_id` | string | yes | External payment ID. Unique in the `wallets` table |
| `recipient_id` | string | yes | Recipient `external_id` (must exist in `recipients`) |
| `is_test` | bool | no | Test wallet |
| `operation_type` | string | no | `buy` (default) or `sell` |
| `address_to` | string | conditional | Recipient address. **Required** when `operation_type=sell`, **forbidden** for `buy`. The format is checked per blockchain (`TronAddressRule` for TRC-20, a regexp otherwise) |

**Example**

```bash
curl -X POST 'https://host/api/wallet/trc-20/generate' \
  -H 'X-API-KEY: ***' \
  -H 'Accept: application/json' \
  -d 'external_id=order-12345' \
  -d 'recipient_id=OF-1' \
  -d 'operation_type=buy'
```

**Response `200`** — a `Wallet` object.

**Errors**

| Code | Cause |
|------|-------|
| 400 | Unsupported blockchain, non-unique `external_id`, recipient not found, invalid `address_to` |
| 422 | `{"success":false,"message":"Could not create the wallet"}`, plus a Telegram alert |

---

### `GET /api/wallet/status/{address}`

Current state of a wallet by address.

**Path parameters:** `address` — string, at most 64 characters.

**Response `200`** — a `Wallet` object.
**`404`** — `Could not find such a wallet`.

---

### `GET /api/wallet/list`

Paginated wallet list, sorted by `id` descending.

**Query parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | int | `30` | Clamped to the range 1–100 |
| `page` | int | `1` | Standard Laravel pagination |
| `status` | int\|string | — | Status code or its English name (`new`, `completed`, …). An unrecognized value is ignored |
| `recipient_id` | string | — | Recipient `external_id` |

**Response `200`** — a `Wallet` collection with `data` / `links` / `meta`.

---

### `POST /api/wallet/send/{address}/{type}`

Sends funds from a wallet. Works only for TRC-20 (`SendUsdt` job); for other blockchains the
service returns `false` → `422`.

**Path parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `address` | string | Source wallet address (max 128) |
| `type` | string | `white`, `gray`, `yellow`, `black` (API spelling; the GREY tier is `gray`) |

**Body / query**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to_address` | string | only for `type=black` | Destination address (max 128) |
| `amount` | numeric | no | Partial send. Minimum `1`, rounded to 6 decimal places (USDT precision). If omitted, the whole balance is sent |

Destination selection logic (`WalletService::send`):

- if the wallet already has `address_to`, funds go there;
- if `to_address` is not passed, the recipient's wallet of the matching `type` is used;
- for `type=black` on a receive (buy) operation, the passed `to_address` overrides the stored one (`WalletLog::SET_WALLET_TO` is written).
- **address rule for receive (buy):** for `white|gray|yellow`, both the passed `to_address` and the stored `address_to` must be a wallet of **the same recipient and the same color**, otherwise the request is rejected; a free-form address is allowed only for `black` (the operator specifies it, for example a return address). For payout (sell) the address is not restricted. The same check is repeated in `SendUsdt` / `SendTrx` before sending.

**Validation checks (`SendRequest`)**

- a wallet with this address exists;
- the wallet has a non-zero balance (`The wallet has no balance, nothing to withdraw`);
- `amount` does not exceed the balance (compared at 6-decimal precision);
- for receive (buy) with `white|gray|yellow`: `to_address` and the stored `address_to` are a wallet of this recipient of this color;
- for `black`: `to_address` matches the blockchain regexp, differs from the source address, and **is not a system wallet of the recipient**.

**Response `200`** — a `Wallet` object.

**Errors**

| Code | Cause |
|------|-------|
| 400 | The validation errors above |
| 404 | Wallet not found |
| 422 | `Could not send funds…` — for example a transfer is already running, sending is finished, the recipient has no wallet of the required type, or the blockchain is not TRC-20 |

---

### `POST /api/wallet/activate/{address}`

Returns a wallet to status `NEW` (waiting for incoming funds again).

**Response `200`** — a `Wallet` object (updated).
**`404`** — wallet not found.
**`422`** — `Could not activate the wallet`.

> The check of the original status (`TIME_OUT`/`COMPLETED`) is commented out in the code, so activation works from any status.

---

### `POST /api/wallet/address-to/{address}`

Changes the recipient address. **Only for payout (sell) operations.**

**Request body**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `address_to` | string | yes | New recipient address, max 128, format per the wallet's blockchain |

**Checks (`ChangeAddressToRequest`)**

- the wallet exists (`No wallet with this address was found`);
- `operation_type = sell` (`The recipient address can be changed only for payout (sell) operations`);
- the transfer has not started yet (`Funds are already being sent, the recipient address cannot be changed`);
- the new address differs from the wallet's own address.

**Response `200`** — a `Wallet` object.
**`400`** — validation errors.
**`422`** — `Could not change the recipient address…`.

---

## AML addresses — `AmlController`

File: `app/Http/Controllers/AmlController.php`

### `AmlAddress` object (`AmlAddressResource`)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Record ID |
| `created_at` | string | Creation date |
| `check_finish_at` | string\|null | When the check finished |
| `address` | string | Address being checked |
| `is_new_transactions` | bool | Whether there are new transactions since the previous check |
| `status` | int | Status code, see [AML address statuses](#aml-address-statuses) |
| `status_name` | string | `new`, `process`, `completed`, `error`, `timeout`, `no_new_check`, `in_queue` |
| `blockchain` | string | Blockchain name (`trc-20`, `btc`, …) |
| `amls` | object | Results for each AML service (see below) |
| `aml_risk_score` | float\|null | Risk score from AmlBot only |
| `aml_file_report_link` | string | `/aml/report/{address}` or `""` |
| `response` | mixed | Raw provider response |
| `has_error` | bool | Error flag |
| `is_test` | bool | Test record |
| `current_tx_count` | int | Current number of transactions |
| `last_check_tx_count` | int | Number of transactions at the time of the previous check |
| `hashes` | object | `current_transaction_hash`, `last_check_transaction_hash` |
| `trx_hashes` | object | `last_check_trx_transaction_hash`, `current_trx_transaction_hash` |

### AML check object

An element of the `amls` dictionary (key — service code: `amlbot`, `wellwallet`, `getblock`, `amlbitok`, `coinkyt`).
Only **completed** checks appear in `amls`.

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Check ID |
| `name` | string | Service name |
| `code` | string | Service code |
| `risk_score` | float | Normalized risk score (0–1) |
| `report_link` | string | Report download link, or `""` |
| `report_file` | string | Direct link to the provider's file (transactions only) |
| `uid` | string\|null | Check identifier at the provider (transactions only) |
| `checked_at` | string\|null | ISO-8601 |
| `signals` | object | `{"low": {...}, "middle": {...}, "high": {...}}` — shares by source category |
| `counterparty` | object | `{"Binance": 70, "OKX": 30}` — counterparties and their shares |

---

### `POST /api/aml/{blockchain}/{address}`

Starts an AML check of an address.

**Path parameters**

| Parameter | Type | Description |
|-----------|------|-------------|
| `blockchain` | string | One of the 26 supported, see [blockchains for AML](#blockchains-for-aml) |
| `address` | string | Address; the format is checked by `AddressRule` per blockchain |

**Body**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `is_test` | bool | no | Test check |

If a record for the "address + blockchain" pair already exists and is marked `is_test`, it is returned immediately without a new check.

**Response `200`** — an `AmlAddress` object.
**`400`** — `Unsupported blockchain type` or an invalid address.
**`202`** — `{"success":false,"message":"Could not check the address"}` (non-standard: an error body with code 202).

---

### `GET /api/aml/status/{blockchain}/{address}` and `GET /api/aml/status/{address}`

Current state of an address AML check. The second form omits the blockchain.

**Parameters:** `blockchain` — optional, max 64; `address` — required, max 64.

**Response `200`** — an `AmlAddress` object.
**`404`** — `Could not find such an address`.

---

## Transactions — `TransactionController`

File: `app/Http/Controllers/TransactionController.php`

### `Transaction` object (`TransactionResource`)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Transaction ID |
| `tx_id` | string | Hash of the incoming transaction |
| `status` | int | Status code, see [transaction statuses](#transaction-statuses) |
| `status_name` | string | Human-readable status |
| `status_name_en` | string | Machine-readable status |
| `from_address` | string | Sender address |
| `created_at` | int | Unix timestamp |
| `amount` | float | Amount |
| `send_tx_hash` | string\|null | Hash of the outgoing transaction |
| `amls` | object | AML results per service (see [AML check object](#aml-check-object)) |
| `signals` | object | Grouped `low`/`middle`/`high` signals (aggregate) |
| `aml_risk_score` | float\|null | Risk score from AmlBot only |
| `aml_file_report_link` | string | `/transaction/report/{id}` or `""` |
| `counterparty` | object | Counterparties |

---

### `POST /api/transaction/{hash}/run-aml`

Starts an AML check of a transaction by its hash.

**Path parameters:** `hash` — the transaction `tx_id`.

Behavior:

- if the transaction already has an `aml_file_report_link`, no check is started and the current state is returned immediately;
- otherwise `WalletService::runAmlTransaction()` is called and the model is reloaded.

**Response `200`** — a `Transaction` object.
**`404`** — `Could not find the transaction`.

---

## Recipients — `RecipientController`

File: `app/Http/Controllers/RecipientController.php`

### `Recipient` object (`RecipientResource`)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Recipient ID |
| `external_id` | string | External ID (the value used as `recipient_id` in other endpoints) |
| `external_id_auth_bot` | string\|null | External ID for bot authorization |
| `name` | string | Name |
| `comment` | string\|null | Comment |
| `wallets` | array | Recipient wallets, only if loaded |
| `ledger_accounts` | array | Ledger accounts shown in the bot — only if the recipient has `ledger_user_id` set |

Recipient wallet (`RecipientWalletResource`): `id`, `address`, `type`, `type_name` (label), `type_name_en` (`white`/`gray`/`yellow`/`black`), `blockchain_type`, `blockchain_type_name`.

Ledger account (`LedgerAccountResource`): `id`, `ledger_id`, `name`, `currency`, `amount`, `wallet_address`.

---

### `GET /api/recipients`

**Query parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | int | `30` | Clamped to the range 1–**30** |
| `page` | int | `1` | |
| `with_wallets` | bool | `true` | Load `wallets` |

**Response `200`** — a paginated `Recipient` collection.

---

### `GET /api/recipients/{identifier}`

Universal recipient lookup. `identifier` is truncated to 22 characters and interpreted as follows:

- digits only → lookup by `id`;
- otherwise → exact match on `external_id` **or** partial match (`LIKE %...%`) on `name`.

The `wallets` relation is always loaded.

**Response `200`** — a `Recipient` object.
**`404`** — `Recipient not found`.

---

## Energy wallets — `EnergyWalletController`

File: `app/Http/Controllers/EnergyWalletController.php`

### `EnergyWallet` object (`EnergyWalletResource`)

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | ID |
| `address` | string | TRON address |
| `label` | string\|null | Label |
| `auto_replenishment_enabled` | bool | Automatic energy top-up is enabled |
| `expires_at` | string\|null | ISO-8601, until when energy is paid for |
| `is_error` | bool | The last provider API call ended with an error |
| `tronscan_link` | string | Link to tronscan |
| `deleted_at` | string\|null | ISO-8601, if the record is soft-deleted |
| `recipient` | object | Recipient — only if the relation is loaded |

---

### `GET /api/energy-wallets`

**Query parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | int | `30` | Clamped to the range 1–100 |
| `page` | int | `1` | Not applied in search mode |
| `search` | string | — | Search by `address` or `label` (`LIKE %...%`) |
| `only_enabled` | bool | `false` | Only wallets with automatic top-up enabled. Ignored when `search` is set |
| `with_recipient` | bool | `false` | See the warning below |

> **Important:** `with_recipient=false` (the default) means `withoutRecipient = true` — the result
> is limited to wallets **not linked to a recipient wallet** (`recipient_wallet_id IS NULL`).
> To get the whole pool, including linked wallets, with the recipient loaded, pass `with_recipient=true`.

**Response `200`** — for a regular listing:

```json
{
  "data": [ { "id": 1, "address": "TR...", "...": "..." } ],
  "meta": { "total": 84, "page": 1, "per_page": 30 }
}
```

With `search` — only `{"data": [...]}`, without `meta`.

---

### `GET /api/energy-wallets/{id}`

**Response `200`** — an `EnergyWallet` object with `recipient` loaded.
**`404`** — `Wallet not found`.

---

## Ledger accounts — `LedgerAccountController`

File: `app/Http/Controllers/LedgerAccountController.php`. Middleware: `api.key`, `api.access_ip`.

Optional integration: a proxy to the external ledger (`LedgerService`) that mirrors the result in the
`ledger_account_wallets` table. Without `LEDGER_API_URL` / `LEDGER_API_TOKEN` both endpoints respond
**`503`** `Ledger integration is not configured`. Details: [LEDGER_INTEGRATION.md](LEDGER_INTEGRATION.md).

### `POST /api/ledger-accounts/{ledgerId}/wallet`

Links a wallet to a ledger account.

**Path parameters:** `ledgerId` — the value of the `ledger_id` field (ledger account UUID).

**Request body**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `wallet_address` | string | yes | TRON address, checked by `TronAddressRule` |
| `network` | string | no | Defaults to `tron` |

**Response `200`**

```json
{
  "success": true,
  "ledger_wallet_id": "uuid",
  "wallet_address": "TR...",
  "network": "tron"
}
```

**External API error** — the status from the ledger response, or `502` if the ledger is unreachable:

```json
{
  "success": false,
  "message": "the ledger's error text, or \"Ledger API error\"",
  "details": { "...": "raw response of the external API" }
}
```

**`404`** — `Ledger account not found`.
**`422`** — standard Laravel validation error (`$request->validate()`, not `ApiRequest`).
**`503`** — integration not configured.

### `DELETE /api/ledger-accounts/{ledgerId}/wallet/{ledgerWalletId}`

Unlinks a wallet from an account. After a successful ledger response the local record is deleted.

**Response `200`** — `{"success": true}`.
**`404`** — `Ledger account not found`.
**`503`** — integration not configured.
**External API error** — same format as above.

---

## Web endpoints (reports)

Routes from `routes/web.php`. **Without** `api.key` / `api.access_ip` — available by direct link
(links to them are returned in the `aml_file_report_link` and `report_link` fields).

| Method | Path | Name | Controller |
|--------|------|------|------------|
| GET | `/wallet/report/{address}` | `wallet.report` | `WalletController@report` |
| GET | `/aml/report/{address}` | `aml.report` | `AmlController@report` |
| GET | `/transaction/report/{id}` | `transaction.report` | `TransactionController@report` |
| GET | `/aml-report/address/{checkId}` | `aml.report.address` | `AmlReportController@downloadAddressReport` |
| GET | `/aml-report/transaction/{checkId}` | `aml.report.transaction` | `AmlReportController@downloadTransactionReport` |

### `GET /wallet/report/{address}`, `GET /aml/report/{address}`, `GET /transaction/report/{id}`

Shared logic: the latest completed check with a report link is taken →
a `302` redirect to `/aml-report/address/{checkId}` or `/aml-report/transaction/{checkId}`.
If there are no new checks but the legacy `aml_file_report_link` field exists, the file is downloaded
from the external link and returned as a stream (`Content-Disposition: attachment`).

**Errors**

| Code | Body |
|------|------|
| 404 | `Could not find such a wallet` / `Could not find such an address` / `Could not find such a transaction` |
| 404 | `Cannot download the file, no link` |
| 404 | `AML report not found` (`/wallet/report/{address}` only) |
| 500 | `{"error": "Could not download the file"}` |

### `GET /aml-report/address/{checkId}` and `/aml-report/transaction/{checkId}`

Return the PDF report of a specific check. Priority: the locally stored file
(`local_report_path` on the `public` disk) → otherwise a download through `AmlServiceDispatcher::downloadReport()`
from the provider that owns the check.

**Errors**

| Code | Body |
|------|------|
| 404 | `{"error": "Check not found"}` |
| 404 | `{"error": "Report file not found"}` |

---

## Webhooks (inbound)

| Method | Path | Name | Handler | Protection |
|--------|------|------|---------|------------|
| POST | `{AML_WEBHOOK_PATH}` (default `/aml/webhook`) | `aml.webhook` | `AmlController@webhook` | HMAC signature, CSRF disabled |
| POST | `/webhook/wallet-listener` | `webhook.wallet-listener` | `WalletWebhookController@handle` | HMAC `X-Signature` |
| POST | `/telegram/webhook` | `telegram.webhook` | `TelegramController@handleWebhook` | `TgSecretTokenCheck` |
| POST | `/telegram/operations/webhook` | `telegram.operations.webhook` | `OperationsTelegramController@handleWebhook` | `TgOperationBotSecretTokenCheck` |

### `POST {AML_WEBHOOK_PATH}`

The path is set by the env variable `AML_WEBHOOK_PATH` (default `/aml/webhook`); use a hard-to-guess value in production.

Receives AML check results from AmlBot.

**Headers:** `x-tonce`, `x-signature`.
The signature is verified as `hash_hmac('sha256', $tonce, config('services.aml.access_key'))`.

**Body (form/post):** key fields — `tx` (transaction hash), `riskscore`.

Behavior: the whole request is logged to the `aml` channel. With a valid signature and a found
transaction, an `AmlLog` is created, `WalletLog::AML_CHECK` is written and the transaction risk is
updated (`AmlBotService::setTransactionRiskOnlyAmlBot`). With an invalid signature, a warning is logged.

**The response is always `200`:** `{"status": "success"}` — including for an invalid signature.

### `POST /webhook/wallet-listener`

Receives events from the external Go service wallet-listener. Accepts `form-encoded` (the main format)
or JSON. The key field is `address`; if a wallet with that address is found, a record is created in
`wallet_webhooks` and `WalletRepository::syncTransactionWebHook()` is called.

**Signature (required).** Header `X-Signature: sha256=<hex hash_hmac('sha256', raw body, WALLET_LISTENER_HMAC_SECRET)>`.
The secret is passed to the listener in `webhooks[].secret` when an address is registered for tracking
(`WalletListenerService::sendWallet`). Verification happens before the body is parsed and before any DB write;
a missing signature, an invalid signature or an unset secret results in `401 {"success": false, "message": "Unauthorized"}`
and a warning in the `wallet_webhook` channel (without the body or the secret).

**Body fields** (all optional, stored in `wallet_webhooks`, the raw body in `payload`)

| Field | Column | Description |
|-------|--------|-------------|
| `address` | — | Wallet address; used to look up the record. Without it, processing is skipped |
| `network` | `network` | Network |
| `token` | `token` | Token |
| `txid` | `txid` | Transaction hash |
| `block` | `block` | Block number |
| `from` | `from_address` | Sender |
| `to` | `to_address` | Recipient |
| `amount` | `amount` | Amount |
| `amount_raw` | `amount_raw` | Amount in minimal units |
| `direction` | `direction` | Direction (`in`/`out`) |
| `ts` | `event_ts` | Event time, parsed with `Carbon::parse()` |

Everything is written to the `wallet_webhook` log channel.

**Response `200`**

```json
{ "success": true, "message": "Webhook received", "received_at": "2026-09-10 12:00:00" }
```

**Response `500`**

```json
{ "success": false, "message": "Webhook processing error", "received_at": "2026-09-10 12:00:00" }
```


### Telegram webhooks

`BaseTelegramController@handleWebhook` is shared by both bots:

1. Reads the update via `Telegram::getWebhookUpdate()`.
2. Deduplication: `update_id` is atomically marked in Redis (`tg_update:{bot}:{id}`, TTL 24 h) —
   repeated Telegram deliveries are ignored.
3. `checkAccess()` — checks the user against the recipients' Telegram users (`tg_users`); rejections are written to the `security` channel.
4. `handleUpdate()` — command/callback handling in the specific controller.

The body is a standard Telegram Update. The response is an empty `200`.

---

## Utility endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Welcome page |
| GET | `/up` | Laravel health check (`withRouting(health: '/up')`) |
| POST | `/{platform.prefix}/admin-login` | Orchid admin panel login, `LoginController@login` |
| GET | `{LOG_VIEWER_ROUTE_PATH}` | Log viewer (opcodesio/log-viewer). Default path is `/log-viewer`; access is open to any authenticated admin panel user; the `LogViewerBasicAuth` middleware in `config/log-viewer.php` is commented out |

### `POST /{platform.prefix}/admin-login`

Overridden Orchid login with IP blocking and 2FA.

**Body:** `email` (required), `password` (required), `code` (nullable — TOTP), `remember` (bool).

Behavior:

- the IP is checked through `BlockedIpService`; when blocked, a `ValidationException` is thrown with the text
  `IP address is blocked. Try again in N minutes.` or `…blocked permanently.`;
- a wrong email/password pair increments the attempt counter for the IP;
- with `is_two_factor_enabled`, a valid `code` (Google2FA) is required, otherwise the error is
  `Enter the two-factor authentication code` /
  `Invalid two-factor authentication code`;
- a successful login resets the attempt counter.

---

## Value reference

### Blockchains for wallet generation

`Recipient\Wallet::blockChainTypesNames()` — used in `POST /api/wallet/{blockchain}/generate`:

| Code | Name | Address format |
|------|------|----------------|
| 1 | `trc-20` | `/^T[1-9A-HJ-NP-Za-km-z]{33}$/` |
| 2 | `btc` | `/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$\|^(bc1)[0-9A-Za-z]{25,39}$/` |
| 3 | `eth` | `/^0x[a-fA-F0-9]{40}$/` |

### Blockchains for AML

`Aml\AmlAddress::blockChainTypesNames()` — used in `/api/aml/*`:

`trc-20`, `btc`, `eth`, `bch`, `ltc`, `xrp`, `etc`, `ada`, `algo`, `xlm`, `matic`, `op`, `base`,
`zec`, `doge`, `solana`, `bsv`, `avax`, `dot`, `ton`, `xtz`, `tether-omni`, `near`, `bsc`, `apt`, `arb`

### Wallet types (`type`)

| Code | `type_name_en` | Label (`type_name`) |
|------|----------------|---------------------|
| 1 | `white` | White |
| 2 | `gray` | Gray |
| 3 | `black` | Black |
| 4 | `yellow` | Yellow |

### Operation types (`operation_type`)

| Code | Name | Description |
|------|------|-------------|
| 1 | `buy` | Receive — the payer sends crypto to a transit wallet (default) |
| 2 | `sell` | Payout — crypto goes to the specified recipient address, no AML check |

### Wallet statuses

| Code | `status_name_en` | `status_name` |
|------|------------------|-------------------------|
| 1 | `new` | New |
| 2 | `add_transaction` | Transaction added |
| 3 | `check_transaction_start` | Transaction check started |
| 4 | `check_transaction_finish` | Transaction check finished |
| 5 | `transfer_money_process_start` | Funds transfer initiated |
| 6 | `transfer_money_process_finish` | Funds transfer finished |
| 10 | `completed` | Completed |
| 11 | `time_out` | Timed out |

### Transaction statuses

| Code | `status_name_en` | `status_name` |
|------|------------------|---------------|
| 1 | `new` | new |
| 2 | `process` | process |
| 3 | `aml_completed_successfully` | AML testing was completed successfully |
| 4 | `aml_not_successful` | AML testing was not successful |
| 5 | `aml_error` | Aml error |

### AML address statuses

| Code | `status_name` |
|------|---------------|
| 1 | `new` |
| 2 | `process` |
| 3 | `completed` |
| 4 | `error` |
| 5 | `timeout` |
| 6 | `no_new_check` |
| 7 | `in_queue` |

### AML service codes

Keys of the `amls` dictionary: `amlbot`, `wellwallet`, `getblock`, `amlbitok`, `coinkyt`.

### AI check colors

`WHITE`, `YELLOW`, `GREY`, `BLACK` — the rule for distributing funds across the recipient's wallets,
see `docs/aml-prompt.md`.

---

## Related documentation

- [LEDGER_INTEGRATION.md](LEDGER_INTEGRATION.md) — optional ledger integration (account bookkeeping) and its API contract
- `docs/aml-prompt.md` — the AI AML check and color-marking prompt
