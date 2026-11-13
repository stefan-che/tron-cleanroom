<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'aml' => [
        'access_id' => env('AML_ACCESS_ID'),
        'access_key' => env('AML_ACCESS_KEY'),
        // Inbound AMLBot webhook route (register the full URL at AMLBot).
        'webhook_path' => env('AML_WEBHOOK_PATH') ?: '/aml/webhook', // empty = default
    ],

    'elliptic_aml' => [
        'access_key' => env('AML_ELLIPTIC_ACCESS_KEY'),
        'access_secret' => env('AML_ELLIPTIC_ACCESS_SECRET'),
    ],

    'getblock' => [
        'token' => env('AML_GETBLOCK_TOKEN'),
    ],

    'aml_bitok' => [
        'key_id' => env('AML_BITOK_KEY_ID'),
        'secret_key' => env('AML_BITOK_SECRET_KEY'),
    ],

    'refee' => [
        'token' => env('REFEE_TOKEN'),
    ],

    'tr_energy' => [
        'api_key' => env('TR_ENERGY_API_KEY'),
    ],

    'netts' => [
        'api_key' => env('NETTS_API_KEY'),
    ],

    'wallet_listener' => [
        'url' => env('WALLET_LISTENER_SERVICE_URL'),
        // Despite the name, this is the cleanroom-watcher API key, sent as the X-API-Key header in requests to /api/watch.
        'webhook_secret' => env('WALLET_LISTENER_WEBHOOK_SECRET', ''),
        // HMAC signing secret for incoming webhooks: the listener sends X-Signature: sha256=<hmac(body)>.
        // Empty value = all webhooks are rejected (fail closed).
        'hmac_secret' => env('WALLET_LISTENER_HMAC_SECRET', ''),
    ],

    /*
     * Recursive AML service: builds a counterparty graph from a root address.
     * Empty traversal parameters are not sent; the service uses its own defaults.
     */
    'recursive_aml' => [
        'enabled' => (bool)env('RECURSIVE_AML_ENABLED', false),
        'url' => env('RECURSIVE_AML_URL'),
        'api_key' => env('RECURSIVE_AML_API_KEY', ''),
    ],

    'etherscan' => [
        'api_key' => env('ETHERSCAN_API_KEY', ''),
    ],

    'blockcypher' => [
        'token' => env('BLOCKCYPHER_TOKEN', ''),
    ],
    'bscscan' => [
        'api_key' => env('BSCSCAN_API_KEY', ''),
    ],

    'polygonscan' => [
        'api_key' => env('POLYGONSCAN_API_KEY', ''),
    ],

    'arbiscan' => [
        'api_key' => env('ARBISCAN_API_KEY', ''),
    ],

    'optimism_etherscan' => [
        'api_key' => env('OPTIMISM_ETHERSCAN_API_KEY', ''),
    ],

    'basescan' => [
        'api_key' => env('BASESCAN_API_KEY', ''),
    ],

    'snowtrace' => [ //avax
        'api_key' => env('SNOWTRACE_API_KEY', ''),
    ],

    'well_wallet' => [
        'base_url' => env('WELL_WALLET_BASE_URL', 'https://tag.alfa-bot.com/api/prod'),
        'api_key' => env('WELL_WALLET_API_KEY', ''),
    ],

    'coinkyt' => [
        'api_key' => env('AML_COINKYT_API_KEY'),
        'report_language' => env('AML_COINKYT_REPORT_LANGUAGE', 'en'),
        'risk_profile_id' => env('AML_COINKYT_RISK_PROFILE_ID', 270),
        // Comma-separated recipient external_id values with CoinKYT enabled. Empty = all.
        'recipients' => array_values(array_filter(array_map('trim', explode(',', (string)env('AML_COINKYT_RECIPIENTS', ''))))),
    ],

    // AI providers for verifying AML results
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        // Admin API key (sk-admin-...), needed only for usage cost statistics
        'admin_api_key' => env('OPENAI_ADMIN_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'admin_api_key' => env('ANTHROPIC_ADMIN_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-opus-4-8'),
    ],

    // External ledger (account bookkeeping), optional: empty url/token disables the integration
    'ledger' => [
        'url'   => env('LEDGER_API_URL', ''),
        'token' => env('LEDGER_API_TOKEN', ''),
    ],

];
