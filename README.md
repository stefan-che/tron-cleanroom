# tron-cleanroom — crypto hygiene for incoming USDT on TRON

[![CI](https://github.com/stefan-che/tron-cleanroom/actions/workflows/ci.yml/badge.svg)](https://github.com/stefan-che/tron-cleanroom/actions/workflows/ci.yml) [![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Every payment lands on its own transit wallet, is AML-screened by the providers you choose,
and only then is swept to your wallets by risk tier.**

**Status:** Production-proven: built and run in production as the processing stack of WOL, a Dubai OTC desk, 2022–2026.

## What is crypto hygiene

Funds of unknown origin never land on your main wallet directly. Each payment gets its own
isolated transit wallet. Incoming funds are screened by the AML providers you choose and given a
risk tier: WHITE, YELLOW, GREY or BLACK (`GREY` is the AML verdict value; the matching destination
wallet type is called Gray, `gray` in the API). Clean funds are swept to your wallets by tier; high-risk
funds stay isolated and go to an address you specify. Your main wallets keep a clean transaction
history, which reduces the risk of exchange holds and counterparty questions.

## Why teams run it

- **A clean history on your main wallets.** Money reaches your operating wallets only after screening and only through the wallet assigned to its tier. A payout guard enforces that route.
- **One `POST` → one fresh TRC-20 transit address** tied to your `external_id`. Your backend polls `GET /api/wallet/status/{address}`.
- **Your providers, your thresholds, your wallets.** You pick the AML providers and the amount above which a check runs; AMLBot is enabled by default with your AMLBot keys. Each recipient has up to four destination wallets, one per tier. PDF reports are stored on your server.
- **Everything on your servers.** Keys, data and reports stay in your infrastructure. Per-payment keys are sealed in a separate split-key vault (cleanroom-vault).
- **Admin panel and Telegram.** Operations, recipients, AML results and alerts (nodes, provider balances, stuck wallets) in the admin panel and two Telegram bots.
- **Lower TRX fees.** TRON energy is rented per sweep (ReFee by default), which reduces the TRX spent on transfers.

## How money moves

```
your backend ──POST /api/wallet/trc-20/generate──► tron-cleanroom ──► cleanroom-vault (seal key)
     │                                                  │
     └──GET /api/wallet/status/{address} (poll)         └──► cleanroom-watcher (watch address)

customer ──USDT──► transit address ──► cleanroom-watcher ──signed webhook──► tron-cleanroom
                                                                           │
                                         AML providers ◄───────────────────┘
                                               │ risk tier
                                               ▼
              decision: your backend (API) · operator (admin panel or Telegram bot)
                                               │
                     ┌─────────────────────────┴──────────────────────┐
                     ▼                                                ▼
   WHITE / YELLOW / GREY: sweep with rented energy      BLACK: isolated, sent to an
   ──► recipient's wallet for that tier                  address you specify
```

Sell wallets skip AML: the destination is fixed at creation and funds are sent once they arrive
(if auto-send is on in settings). Unpaid wallets expire.

## The stack

| Repository | Role | Runtime |
|---|---|---|
| **tron-cleanroom** (this repo, core) | Transit wallets, AML screening, tier routing, sweeps, admin panel, two Telegram bots, REST API | PHP 8.4, Laravel 11, MySQL 8, Redis |
| [cleanroom-watcher](https://github.com/stefan-che/cleanroom-watcher) — **required** | Watches transit wallets on TRON and reports incoming TRX and USDT with signed webhooks | Go, MySQL, Redis optional |
| [cleanroom-vault](https://github.com/stefan-che/cleanroom-vault) — **required** | Split-key vault for per-payment private keys, called as a CLI | Go, MySQL |
| [Tally](https://github.com/stefan-che/tally-bot) (`tally-bot`) — optional | Team bookkeeping in Telegram: manual entries and automatic balances for tracked wallets; optional integration with tron-cleanroom | Python, PostgreSQL |

How the parts connect, where keys live and the exact contracts: [ARCHITECTURE.md](ARCHITECTURE.md).

## Capabilities

| Capability | Default |
|---|---|
| Transit wallet per payment (TRON, USDT TRC-20), key sealed by cleanroom-vault | on |
| Arrival detection: signed cleanroom-watcher webhook plus a catch-up cron | on |
| AML threshold (start checks above a set amount) | on, configurable |
| AMLBot screening, PDF reports | on, needs your AMLBot account |
| WellWallet, GetBlock, BitOK, CoinKYT screening | optional, needs an account |
| AI risk tier (OpenAI), advisory | optional, off, needs an account |
| Tier routing and payout destination guard | on |
| Energy rental: ReFee / Tr.energy, Netts | ReFee on (needs an account) / optional |
| TRON node failover (your nodes, TronGrid last) | on, needs a node or a TronGrid key |
| Two Telegram bots (recipients, operations) and alerts | optional, needs bot tokens |
| Ledger integration with Tally (tally-bot) ([docs](docs/LEDGER_INTEGRATION.md)) | optional, off until configured |
| Encrypted backup of keys to a second database | optional, off |

## Scope

| | |
|---|---|
| Network | TRON mainnet |
| Assets | USDT TRC-20 and TRX |
| Interfaces | Admin panel, bots, API and docs in English ([API reference](docs/API_REFERENCE.md)) |
| Providers | Your own accounts: AML provider(s), energy provider, a TRON node or TronGrid key |

## Quickstart

Full guide: [docs/QUICKSTART.md](docs/QUICKSTART.md). In short: clone `tron-cleanroom`,
`cleanroom-watcher`, `cleanroom-vault` (and optionally `tally-bot`) side by side, run `make bootstrap`
in `tron-cleanroom/docker/` (builds the encryptor, creates the vault, migrates and seeds), then
start cleanroom-watcher.

## Docs

- [ARCHITECTURE.md](ARCHITECTURE.md) — components, trust boundaries, cross-repo contracts
- [docs/QUICKSTART.md](docs/QUICKSTART.md) — run the stack on one host
- [docs/CONFIGURATION.md](docs/CONFIGURATION.md) — environment variables
- [docs/API_REFERENCE.md](docs/API_REFERENCE.md) — REST API, statuses, webhooks
- [docs/LEDGER_INTEGRATION.md](docs/LEDGER_INTEGRATION.md) — optional tally-bot link
- [SECURITY.md](SECURITY.md) — security policy for the whole stack
- `docker/prod/README.md` — production stack

## Security

[SECURITY.md](SECURITY.md) is the single security policy for all four repositories: built-in
protection, the production hardening checklist and how to report an issue privately.

## Third-party code

`packages/web3.php` and `packages/comely-data-types` are vendored copies of MIT-licensed
packages; see `VENDORED.md` and `LICENSE` in each folder.

## Contributing and license

External contributions are not accepted yet.
Licensed under the MIT License — see [LICENSE](LICENSE).
