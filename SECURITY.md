# Security

This is the single security policy for the stack: tron-cleanroom (this repository), [cleanroom-watcher](https://github.com/stefan-che/cleanroom-watcher), [cleanroom-vault](https://github.com/stefan-che/cleanroom-vault) and [tally-bot](https://github.com/stefan-che/tally-bot). Component repositories may add component details in their own SECURITY.md; reporting always goes through the channel below. How the parts connect: [ARCHITECTURE.md](ARCHITECTURE.md).

## Reporting a security issue

Please do not open a public issue. Report privately through GitHub: [Report a vulnerability](https://github.com/stefan-che/tron-cleanroom/security/advisories/new) (Security tab of this repository). This covers all four repositories.

Include the component, the version or commit, steps to reproduce, and the impact you see. We will acknowledge the report and agree on a disclosure date with you.

## Supported versions

Fixes land on the main branch of each repository.

## Built-in protection

- **Split-key vault for private keys (cleanroom-vault).** Each key is sealed with its own random AES key, split between the vault database and the application. The master key is wrapped by a KEK file. The encryptor stops before any database connection if the KEK or the master key is missing. The vault database user can only SELECT and INSERT. Keys are decrypted only for a sweep and never stored in plain text.
- **Signed listener webhooks.** cleanroom-watcher signs each webhook with HMAC-SHA256 over the raw body. tron-cleanroom checks the signature in constant time before parsing; a missing secret or a bad signature returns 401. Deliveries are persisted and retried.
- **Payout destination guard per risk tier.** WHITE, YELLOW and GREY funds go only to the same recipient's wallet of the same tier. Only BLACK may go to an address you specify. The rule is re-checked right before sending.
- **Admin access in tron-cleanroom.** Roles, Google Authenticator 2FA for admin users, and IP blocking after failed logins.
- **API access control.** In tron-cleanroom: API keys (`X-API-KEY`, plus a read-only key) and an IP allow-list; mismatches return 403 and are written to the `security` log. API keys in cleanroom-watcher and tally-bot. tally-bot refuses API requests when its token is not set and refuses admin logins without `ADMIN_PASSWORD`.
- **Telegram secret tokens.** Both tron-cleanroom bots check the secret-token header on every webhook update.

## Production hardening checklist

Complete these steps before any component faces the internet.

- [ ] **Set the tron-cleanroom API keys and IP allow-list.** Set `GLOBAL_OUR_API_KEY` (and `GLOBAL_READONLY_API_KEY` if you use read-only routes) and fill the API IP allow-list in the admin settings.
- [ ] **Configure the trusted proxy.** Put tron-cleanroom behind your reverse proxy and let only that proxy (or Cloudflare) set the `CF-Connecting-IP` header; strip it from incoming client requests.
- [ ] **Serve report URLs and admin tools behind your reverse-proxy authentication.** This covers the report URLs (`/wallet/report/*`, `/aml/report/*`, `/aml-report/*`, `/transaction/report/*`), the log viewer and the cleanroom-watcher admin UI. To switch the log viewer off, set `LOG_VIEWER_ENABLED=false`.
- [ ] **Use private paths.** Set `AML_WEBHOOK_PATH` and `LOG_VIEWER_ROUTE_PATH` to private values, and set `AML_ACCESS_KEY` so AMLBot webhooks are verified.
- [ ] **Set the webhook secrets.** Set `WALLET_LISTENER_HMAC_SECRET`, `TELEGRAM_SECRET_TOKEN` and `TELEGRAM_OPERATIONS_SECRET_TOKEN` to random values.
- [ ] **Set cleanroom-watcher credentials.** Fill `api.api_keys` and set a long random admin password and session secret in `config.json`; keep the admin path behind the nginx IP allow-list.
- [ ] **Enable 2FA** for every admin user of tron-cleanroom (`OTP_ENABLED`, then in each user profile).
- [ ] **Separate the databases.** Store the application database and the key vault — and their backups — on separate hosts with separate credentials. Restrict the encryptor binary, `enc/config.json` and `enc/kek` to the application user.
- [ ] **Restrict network access.** Keep cleanroom-watcher, the vault MySQL and the other databases on a private network; do not publish their ports.
- [ ] **Rotate secrets** that came from example files, then on a schedule: API keys, HMAC secrets, Telegram tokens, DB passwords, `LEDGER_API_TOKEN`, tally-bot `API_TOKEN` and `ADMIN_PASSWORD`, the cleanroom-vault master key and `kek` (see [cleanroom-vault MAINTENANCE.md](https://github.com/stefan-che/cleanroom-vault/blob/main/MAINTENANCE.md)).
- [ ] **Protect logs.** Restrict access to the `security` log channel and rotate it.
- [ ] **Start with small amounts.** The stack runs on TRON mainnet; run your first payments with small sums.

## Operator responsibilities

You operate the stack on your own infrastructure and are responsible for key management, infrastructure hardening and regulatory obligations (AML/KYC, licensing) in your jurisdiction. AML scores are risk signals from the providers you choose, not legal advice.
