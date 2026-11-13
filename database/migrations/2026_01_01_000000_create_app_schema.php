<?php

use App\Enums\AmlAddressStatuses;
use App\Enums\TransactionStatuses;
use App\Enums\WalletOperationsTypes;
use App\Enums\WalletStatuses;
use App\Models\Wallet\Wallet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Full application schema (all migrations up to 2026-08-27 squashed). Table order follows foreign keys.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->string('telegram_id', 64)->nullable();
            $table->string('last_login_ip_address', 32)->nullable();
            $table->string('allow_ips', 500)
                ->comment('Allowed IP addresses')
                ->default('');
        });

        Schema::create('auth_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('location')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('type')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('ip_address');
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->integer('attempts')->default(0);
            $table->timestamp('blocked_until')->nullable();
            $table->string('reason')->default('Too many failed login attempts');
            $table->text('user_agent')->nullable();
            $table->boolean('is_permanently_blocked')->default(true);
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique('ip_address');
            $table->index(['blocked_until', 'is_permanently_blocked']);
            $table->index('unblocked_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value', 800);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('recipients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('external_id', 64)->unique();
            $table->string('name');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->text('comment')->nullable();
            // User ID in the external ledger (optional integration, docs/LEDGER_INTEGRATION.md)
            $table->string('ledger_user_id')->nullable();
        });

        Schema::create('recipient_wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('recipient_id')
                ->constrained('recipients')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('type')
                ->index()
                ->default(1)
                ->comment('white/gray/black');
            $table->unsignedTinyInteger('blockchain_type')
                ->index()
                ->default(1)
                ->comment('blockchain');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->string('address')->unique();
        });

        Schema::create('recipient_wallets_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('wallet_id')
                ->constrained('recipient_wallets')
                ->cascadeOnDelete();
            $table->integer('action');
            $table->jsonb('payload');
            $table->string('telegram_chat_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('tg_users', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_id', 32)->unique();
            $table->string('name')->nullable();
            $table->boolean('can_create_recipients')->default(false);
            $table->boolean('can_manage_ledger_accounts')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recipient_tg_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_id');
            $table->unsignedBigInteger('tg_user_id');
            $table->timestamps();

            $table->foreign('recipient_id')->references('id')->on('recipients')->onDelete('cascade');
            $table->foreign('tg_user_id')->references('id')->on('tg_users')->onDelete('cascade');
            $table->unique(['recipient_id', 'tg_user_id']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('ledger_id', 36);
            $table->string('group_id', 36);
            $table->unsignedBigInteger('recipient_id');
            $table->string('user_id');
            $table->string('name');
            $table->string('currency');
            $table->bigInteger('amount')->default(0);
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->boolean('is_show_bot')->default(false);
            $table->timestamps();

            $table->unique(['ledger_id', 'recipient_id']);
            $table->foreign('recipient_id')->references('id')->on('recipients')->cascadeOnDelete();
            $table->foreign('wallet_id')->references('id')->on('recipient_wallets')->nullOnDelete();
        });

        Schema::create('ledger_account_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ledger_account_id');
            $table->string('ledger_wallet_id', 36);
            $table->string('wallet_address');
            $table->string('network', 32)->default('tron');
            $table->timestamps();

            $table->foreign('ledger_account_id')->references('id')->on('ledger_accounts')->cascadeOnDelete();
        });

        Schema::create('aml_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('site_link')->nullable();
            $table->string('api_doc_link')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('energy_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('site_link')->nullable();
            $table->string('api_doc_link')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('ai_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('model')->nullable();
            $table->string('api_key')->nullable();
            // Provider Admin API key: a regular api_key has no access to usage cost statistics
            $table->string('admin_api_key')->nullable();
            // Providers do not report the remainder: balance = top-up minus spend since the top-up date
            $table->decimal('balance_topup', 12, 2)->nullable();
            $table->timestamp('balance_topup_at')->nullable();
            $table->decimal('balance_threshold', 12, 2)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('priority')->default(1);
            $table->longText('prompt')->nullable();
            $table->string('description')->nullable();
            $table->string('site_link')->nullable();
            $table->string('api_doc_link')->nullable();
            $table->timestamps();
        });

        Schema::create('tron_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('own'); // own - our node, trongrid - public API
            $table->string('full_node');
            $table->string('solidity_node')->nullable();
            $table->string('event_server')->nullable();
            $table->string('api_key')->nullable();
            $table->boolean('is_enabled')->default(true);   // managed manually in settings
            $table->boolean('is_available')->default(true); // set by alerts
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedBigInteger('last_block')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('last_block_changed_at')->nullable();
            $table->timestamp('unavailable_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'is_available', 'priority']);
        });

        Schema::create('wallets_trx', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->double('amount')
                ->comment('TRX amount')
                ->default(0);
            $table->string('address')->unique();
            $table->string('private_key');
            $table->text('comment')->nullable();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('connect_type')->default(Wallet::CRYPTO_DEFAULT_CONNECT);
            $table->boolean('has_error')->default(false);
            $table->mediumText('error')->nullable();
            $table->unsignedTinyInteger('status')->default(WalletStatuses::NEW->value);
            $table->integer('type')->nullable();
            $table->unsignedTinyInteger('operation_type')
                ->default(WalletOperationsTypes::BUY->value)
                ->comment('Operation type: 1 - incoming (buy), 2 - payout (sell)');
            $table->unsignedTinyInteger('blockchain_type');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('recipient_id')
                ->constrained('recipients')
                ->cascadeOnDelete();
            $table->double('amount')
                ->comment('Amount of funds received')
                ->nullable();
            $table->double('last_transfer_amount')
                ->comment('Amount of the last send')
                ->nullable();
            $table->float('amount_energy')
                ->comment('Energy amount')
                ->nullable();
            $table->float('amount_trx')
                ->comment('TRX amount')
                ->nullable();
            $table->string('external_id', 64)->unique();
            $table->string('address')->unique();
            $table->string('address_hex');
            $table->string('address_to')->nullable();
            $table->string('send_tx_hash')->nullable();
            $table->string('private_key');
            $table->string('private_key_secret');
            $table->string('private_key_salt');
            $table->string('private_key_hash');
            $table->string('public_key', 512);
            $table->text('saved_key')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('expire_energy_at')->nullable();
            $table->float('aml_risk_score')->nullable();
            $table->string('aml_file_report_link')->nullable();
            $table->boolean('is_test')->default(false);
            $table->float('energy_balance')
                ->comment('energy balance')
                ->default(0)
                ->nullable();
            $table->decimal('energy_cost_trx', 18, 6)->default(0);
            $table->foreignId('recipient_wallet_id')
                ->nullable()
                ->constrained('recipient_wallets')
                ->cascadeOnDelete();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id')->startingValue(3284);
            $table->unsignedTinyInteger('status')->default(TransactionStatuses::NEW->value);
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();
            $table->foreignId('recipient_id')
                ->constrained('recipients')
                ->cascadeOnDelete();
            $table->double('amount');
            $table->float('aml_risk_score')->nullable();
            $table->string('tx_id')->unique();
            $table->string('aml_file_report_link')->nullable();
            $table->string('from_address');
            $table->jsonb('tx_data');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('send_at')->nullable();
            $table->string('aml_uid')->nullable();
            $table->string('send_tx_hash')->nullable();

            // aml:export groups incoming transactions by sender
            $table->index('from_address', 'transactions_from_address_index');
        });

        Schema::create('wallets_aml_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('aml_service_id')->default(1);
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->jsonb('response');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->float('aml_balance')
                ->comment('aml balance')
                ->default(0)
                ->nullable();

            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('aml_service_id')
                ->references('id')
                ->on('aml_services')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        Schema::create('wallets_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('action');
            $table->string('ip_address', 45)->nullable();
            $table->longText('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('wallet_webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();
            $table->string('network', 32)->nullable();
            $table->string('token', 32)->nullable();
            $table->string('txid', 128)->nullable();
            $table->unsignedBigInteger('block')->nullable();
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->string('amount', 64)->nullable();
            $table->string('amount_raw', 64)->nullable();
            $table->string('direction', 16)->nullable();
            $table->timestamp('event_ts')->nullable()->comment('Event time from the webhook (ts field)');
            $table->jsonb('payload')->nullable()->comment('Full webhook payload');
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('aml_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('has_error')->default(false);
            $table->unsignedTinyInteger('status')->default(AmlAddressStatuses::NEW->value);
            $table->unsignedTinyInteger('blockchain_type');
            $table->string('address');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('check_finish_at')->nullable();
            $table->float('aml_risk_score')->nullable();
            $table->string('aml_file_report_link')->nullable();
            $table->jsonb('response')->nullable();
            $table->text('comment')->nullable();
            $table->text('error')->nullable();
            $table->integer('current_tx_count')
                ->comment('Current transaction count')
                ->default(0);
            $table->integer('last_check_tx_count')
                ->comment('Transaction count at the last check')
                ->default(0);
            $table->string('current_transaction_hash')
                ->comment('Latest hash currently')
                ->nullable();
            $table->string('last_check_transaction_hash')
                ->comment('Latest hash at the last check')
                ->nullable();
            $table->string('current_trx_transaction_hash')
                ->comment('Latest TRX hash currently')
                ->nullable();
            $table->string('last_check_trx_transaction_hash')
                ->comment('Latest TRX hash at the last check')
                ->nullable();
            $table->string('aml_uid')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamp('last_check_hash_at')->nullable();
            $table->float('aml_balance')
                ->comment('aml balance')
                ->default(0)
                ->nullable();

            $table->unique(['blockchain_type', 'address']);
        });

        Schema::create('aml_addresses_checks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('aml_address_id')
                ->constrained('aml_addresses')
                ->cascadeOnDelete();
            $table->integer('current_tx_count')
                ->comment('Current transaction count')
                ->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('check_finish_at')->nullable();
            $table->unsignedTinyInteger('status')->default(AmlAddressStatuses::NEW->value);
            $table->float('aml_risk_score')->nullable();
            $table->string('aml_file_report_link')->nullable();
            $table->string('local_report_path')->nullable();
            $table->jsonb('response')->nullable();
            $table->text('error')->nullable();
            $table->string('current_transaction_hash')
                ->comment('Latest hash currently')
                ->nullable();
            $table->string('last_check_transaction_hash')
                ->comment('Latest hash at the last check')
                ->nullable();
            $table->string('current_trx_transaction_hash')
                ->comment('Latest TRX hash currently')
                ->nullable();
            $table->string('last_check_trx_transaction_hash')
                ->comment('Latest TRX hash at the last check')
                ->nullable();
            $table->float('aml_balance')
                ->comment('aml balance')
                ->default(0)
                ->nullable();
            $table->foreignId('aml_service_id')
                ->nullable()
                ->constrained('aml_services');
            $table->string('uid')->nullable();
            $table->jsonb('signals')->nullable();
            $table->jsonb('counterparty')->nullable();
            $table->softDeletes();
        });

        Schema::create('transactions_aml_checks', function (Blueprint $table) {
            $table->id()->startingValue(2007);
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('status')
                ->default(0)
                ->comment('0=pending, 1=completed, 2=skipped, 3=failed');
            $table->foreignId('service_id')
                ->constrained('aml_services')
                ->cascadeOnDelete();
            $table->float('risk_score')->nullable();
            $table->string('file_report_link')->nullable();
            $table->string('local_report_path')->nullable();
            $table->string('uid')->nullable();
            $table->jsonb('response')->nullable();
            $table->jsonb('signals')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('check_finish_at')->nullable();
            $table->jsonb('counterparty')->nullable();
            $table->softDeletes();
        });

        Schema::create('wallet_ai_aml_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id')->index();
            $table->unsignedBigInteger('ai_service_id')->nullable()->index();
            $table->string('wallet_color')->nullable();  // WHITE | YELLOW | GREY | BLACK
            $table->string('risk')->nullable();          // LOW | MEDIUM | HIGH
            $table->unsignedInteger('confidence')->nullable();
            $table->text('summary')->nullable();
            $table->longText('prompt')->nullable();          // prompt snapshot at check time
            $table->longText('request_payload')->nullable(); // full request: { text, params, data }
            $table->longText('raw_response')->nullable();
            $table->longText('coinkyt_result')->nullable();
            $table->string('coinkyt_wallet_color')->nullable();
            $table->string('coinkyt_risk')->nullable();
            $table->unsignedTinyInteger('status')->default(0); // 0=pending,1=completed,3=failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
        });

        Schema::create('coinkyt_monitored_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id')->nullable()->index();
            $table->string('blockchain', 16)->default('trx'); // btc | eth | trx
            $table->string('address');
            $table->string('token')->default('USDT')->nullable(); // '' native, USDT, USDC
            $table->unsignedBigInteger('risk_profile_id')->nullable();
            $table->string('risk_profile')->nullable();
            $table->boolean('address_changes')->default(true);
            $table->unsignedTinyInteger('status')->default(1); // 1=active, 0=removed
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['blockchain', 'address']);
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
        });

        Schema::create('coinkyt_monitoring_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();     // notification UUID from CoinKYT
            $table->unsignedBigInteger('monitored_address_id')->nullable()->index();
            $table->unsignedBigInteger('wallet_id')->nullable()->index();
            $table->string('tracked_address')->index();  // address being monitored
            $table->string('blockchain', 16)->nullable();
            $table->string('token')->nullable();
            $table->string('risk_profile')->nullable();
            $table->string('risk_grade', 16)->nullable(); // high | moderate | low | undefined
            $table->string('balance')->nullable();
            $table->string('sender')->nullable();
            $table->string('txid')->nullable()->index();
            $table->json('triggered_by')->nullable();    // risk profile trigger signals
            $table->json('address_changes')->nullable(); // indirect exposure changes
            $table->json('raw')->nullable();             // full item from the API
            $table->timestamp('event_at')->nullable()->index(); // date from the response (UTC)
            $table->timestamps();

            $table->foreign('monitored_address_id')->references('id')->on('coinkyt_monitored_addresses')->nullOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();
        });

        Schema::create('energy_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_wallet_id')
                ->nullable()
                ->constrained('recipient_wallets')
                ->nullOnDelete();
            $table->foreignId('recipient_id')
                ->nullable()
                ->constrained('recipients')
                ->nullOnDelete();
            $table->unsignedBigInteger('energy_service_id')
                ->nullable()
                ->comment('Service used for the smart start. Null means not started yet.');
            $table->string('address')->unique();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('label')->nullable();
            $table->boolean('auto_replenishment_enabled')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Automatic disable date. Null means it never expires.');
            $table->boolean('is_error')->default(false);

            $table->foreign('energy_service_id')
                ->references('id')
                ->on('energy_services')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });

        Schema::create('energy_wallets_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('energy_wallets_id')
                ->constrained('energy_wallets')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('energy_service_id')->nullable();
            $table->string('event')->comment('added, enabled, disabled, removed');
            $table->string('changed_by')->nullable();
            $table->string('note')->nullable();
            $table->text('api_response')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('energy_service_id')
                ->references('id')
                ->on('energy_services')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        foreach ([
            'energy_wallets_history',
            'energy_wallets',
            'coinkyt_monitoring_events',
            'coinkyt_monitored_addresses',
            'wallet_ai_aml_checks',
            'transactions_aml_checks',
            'aml_addresses_checks',
            'aml_addresses',
            'wallet_webhooks',
            'wallets_logs',
            'wallets_aml_log',
            'transactions',
            'wallets',
            'wallets_trx',
            'tron_nodes',
            'ai_services',
            'energy_services',
            'aml_services',
            'ledger_account_wallets',
            'ledger_accounts',
            'recipient_tg_user',
            'tg_users',
            'recipient_wallets_log',
            'recipient_wallets',
            'recipients',
            'settings',
            'blocked_ips',
            'auth_logs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::dropColumns('users', [
            'is_two_factor_enabled',
            'two_factor_secret',
            'telegram_id',
            'last_login_ip_address',
            'allow_ips',
        ]);
    }
};
