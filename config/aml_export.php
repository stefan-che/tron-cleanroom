<?php

/**
 * Export of the AML database in `aml-import/v1` format for import into an external AML database.
 */

return [

    'format' => 'aml-import/v1',

    /**
     * Value of the `source` field in the manifest; the receiver uses it to tell
     * exports from different systems apart.
     */
    'source' => env('AML_EXPORT_SOURCE', 'tron-telegram-api'),

    /**
     * Risk score scale in which each service stores its value
     * in `aml_addresses_checks.aml_risk_score`.
     *
     * Derived from the jobs `app/Jobs/Aml/<Service>/AmlAddressCheck.php`:
     *   amlbot     — `riskscore` from the API as is, fraction 0..1;
     *   coinkyt    — `risk_score` * 100;
     *   amlbitok   — `risk_score` * 100;
     *   getblock   — `riskscore` * GetBlockService::RISC_SCORE_MULTIPLIER (10);
     *   wellwallet — `aml_score` as is, 0..100.
     *
     * The scale is the only thing that silently turns risk 85 into risk 8.5,
     * so checks with a value outside the declared range are NOT exported
     * and are counted as `scale_mismatch` (see AmlDatabaseExporter).
     */
    'scales' => [
        'amlbot' => '0-1',
        'wellwallet' => '0-100',
        'coinkyt' => '0-100',
        'amlbitok' => '0-100',
        'getblock' => '0-100',
        'elliptic' => '0-100',
    ],

    /**
     * Scale for a service missing from `scales`. Empty means such checks
     * are skipped with reason `unknown_scale`: the receiver would reject
     * a line without a scale anyway, so better not to guess.
     */
    'default_scale' => null,

    /**
     * Address sources (see App\Services\Aml\Export\AmlExportSources):
     *
     *   transactions — senders of incoming transactions
     *                  (`transactions.from_address` + `transactions_aml_checks`).
     *                  Main source: the address is here because it actually
     *                  sent a transfer to our wallet;
     *   registry     — the `aml_addresses` + `aml_addresses_checks` registry.
     *                  Populated only externally, via
     *                  `POST /api/aml/{blockchain}/{address}`.
     *
     * One address can be in both: the most recent verdict per service
     * is kept.
     */
    'sources' => ['transactions', 'registry'],

    /**
     * Networks exported by default (codes from AmlAddress::amlCodes()).
     * The receiver currently supports only TRX and drops the rest as foreign_network.
     */
    'networks' => ['TRX'],

    /**
     * Raw provider responses (`checks[].raw`). Disabled by default:
     * for 20k addresses they inflate the file by an order of magnitude and are only
     * needed to investigate disputed cases.
     */
    'with_raw' => false,

    /**
     * With `with_raw` enabled, the raw response is included only for addresses
     * whose normalized risk is at or above this threshold.
     */
    'raw_min_score' => 40,

    /**
     * Batch size when fetching addresses.
     */
    'chunk' => 500,

    /**
     * Directory for file exports of the CLI command.
     */
    'dir' => storage_path('exports/aml'),
];
