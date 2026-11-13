# Code rules

The list grows over time. The rules are mandatory for both humans and agents.

They apply to new and modified code. Do not mass-rewrite old comments and labels —
only the ones you touch as part of a task.

## 1. No long essays

A comment explains **why**, in one or two lines. Not a paragraph, not a section,
not the history of the issue.

**In code (PHP, Blade, migrations, configs):**

- A comment is at most **2 lines**. If it does not fit, the explanation belongs
  elsewhere: put it in the relevant file under `docs/` and leave a link to it in the code.
- PHPDoc has the same limit on description text. Write `@param`/`@return`/`@throws`
  tags only when the type or meaning is not visible from the signature.
- Do not restate the code in words. If the method name and body make everything clear,
  no comment is needed at all.
- Do not write history in comments: "it used to be like this, then it broke, then it was
  fixed". That belongs in git and `docs/`.
- Do not leave commented-out code "just in case" without a note on why it is disabled
  and when it comes back (one line).
- A comment must earn its place: a non-obvious invariant, a trap, the meaning of an
  unnamed argument (`false` in a call), the semantics of a return value, a link to a
  document in `docs/`.
- Comments are in English, like identifiers, error codes and log keys.

**In the interface (Orchid, Telegram bots, Blade templates):**

- A hint (`->help()`, `->title()`, a bot message text) is one line. A paragraph of
  instructions under a form field or in a bot message does not get read and hides what
  actually matters.
- Do not label the obvious: if a button is called "Update balance", the line
  "the balance is updated here" is redundant.
- Warn (`->confirm()`, an alert) only about what is irreversible or costs money: the AML
  check is paid, anything sent to the chain cannot be recalled, energy is rented for TRX,
  funds leave immediately.
- Long explanations go in `docs/`, not on the operator's screen.

**Bad** (`app/Services/Aml/AmlColorResolver.php`):

```php
/**
 * Determines the wallet color from the result of ONE AML service.
 *
 * Mirrors the verdict algorithm of the AI prompt (docs/aml-prompt.md, "Verdict algorithm" section),
 * but without calling the AI and without merging services: the AI takes the maximum of each metric
 * across all providers, while here each check is rated separately. Used for reconciliation -
 * it shows which service pushes the final verdict up or down.
 *
 * Units: `signals` values are stored on a scale where 10 = 100% of volume (Wallet::formatSignals),
 * `counterparty` values are already percentages 0-100.
 *
 * IMPORTANT: the thresholds and venue lists below are duplicated in the AI prompt. When changing the policy,
 * update both in one commit: the prompt (`docs/aml-prompt.md` + AiServicesSeeder::defaultPrompt()
 * + the `ai_services` record) and this class's constants. Otherwise the reconciliation report will
 * show discrepancies that do not actually exist.
 */
```

**Good:**

```php
// Wallet color for a single AML service, for reconciliation with the AI verdict.
// Thresholds are duplicated in the prompt: change them only together, see docs/aml-prompt.md.
```

The `signals` units go into a comment on the method that reads them, and the prompt
synchronization procedure goes into the relevant document in `docs/`.

**Bad** (a hint in `AmlExportFormLayout`):

> "Transaction sender addresses" are those who actually sent a transfer to our
> wallet. The aml_addresses registry is filled only through the external API, and
> what exactly the consumer sends there does not follow from our code

**Good:** "Senders are those who sent a transfer to our wallet".

## 2. No technical details in the interface

The operator sees actions and states in their own language, not the internals of the service.
Class and job names, setting keys, enum values, queues, codes and table names belong in
`docs/`, not on screen.

- Do not write in the UI: `TRANSFER_MONEY_PROCESS_START`, `AiAmlCheckJob`,
  `AI_AML_CHECK_ENABLED`, `is_enabled`, "into the Redis queue", "via webhook",
  `transactions_aml_checks`, numeric status codes in square brackets.
- Name a state in words: "waiting for forwarding", "under review", "completed".
  Status labels live in `Wallet::getStatuses()` and `Transaction::getStatuses()`; bot texts
  live in `resources/lang/en/`. A new status ships with its label there right away,
  not the enum's `->name` in a template.
- Who exactly sent a command (cron, API, job, another bot) is none of the operator's
  business: what matters is that the decision is not theirs. Say it that way.
- Provider names (AmlBot, BitOK, Refee, Netts) are fine: the operator works with them.
  Their endpoints, parameters and raw responses are not.
- The exception is incident-analysis pages (log viewer, wallet logs, AML reports,
  service screens for testing statuses): there the technical value is the content.

**Bad** (`AmlExportFormLayout`, provider screen):

- "The receiver currently accepts only TRX, other networks it will discard as
  foreign_network"
- "…The right fix is to edit config/aml_export.php → scales"
- `Toast::error('AML API request error: ' . json_encode($result['body']))`: the raw API
  response on the operator's screen.

**Good:**

- "The receiver accepts only TRX for now; other networks will not be included in the export"
- "Risk on a foreign scale can look like a clean address" (how to fix it goes in the
  relevant document in `docs/`)
- "The AML service did not respond, try again later", and the response body goes to the log.
