<?php
/**
 * php artisan db:seed --class=SettingsSeeder
 */

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        Setting::firstOrCreate(['key' => Setting::AML_SUM_CHECK,], [
            'value' => 101,
            'description' => 'Amount from which the AML check is triggered',
        ]);

        Setting::firstOrCreate(['key' => Setting::TRX_AMOUNT,], [
            'value' => 30,
            'description' => 'TRX amount for sending USDT (Fee Limit)',
        ]);

        Setting::firstOrCreate(['key' => Setting::COUNT_ENERGY_SEND_TR,], [
            'value' => 65000,
            'description' => 'Energy amount for sending USDT',
        ]);

        Setting::firstOrCreate(['key' => Setting::ALERT_ENERGY_MIN_SUM_CHECK,], [
            'value' => 450,
            'description' => 'Minimum energy balance that triggers a top-up notification',
        ]);

        Setting::firstOrCreate(['key' => Setting::COUNT_TRX_FOR_ACTIVATE_WALLET,], [
            'value' => 0.0001,
            'description' => 'TRX amount for wallet activation (min:0.0001)',
        ]);

//        Setting::firstOrCreate(['key' => Setting::TELEGRAM_USERS_ID,], [
//            'value' => '',
//            'description' => 'DEPRECATED List of telegram user IDs ',
//        ]);

        Setting::firstOrCreate(['key' => Setting::MINIMUM_TR_AMOUNT,], [
            'value' => '2',
            'description' => 'Minimum transaction amount (dust protection)',
        ]);

        Setting::firstOrCreate(['key' => Setting::ACCESS_API_IP,], [
            'value' => '',
            'description' => 'IPs allowed to access the API (comma-separated)',
        ]);

        Setting::firstOrCreate(['key' => Setting::AML_BOT_FLOW_MODE,], [
            'value' => 'fast',
            'description' => 'Check mode in AML bot (advanced, fast, instant)',
        ]);

        Setting::firstOrCreate(['key' => Setting::AUTO_SEND_SELL_ENABLED,], [
            'value' => '0',
            'description' => 'Automatic sending for payout (sell) operations (1 - enabled, 0 - disabled)',
        ]);

        Setting::firstOrCreate(['key' => Setting::OPERATIONS_BOT_TELEGRAM_USERS_ID,], [
            'value' => '',
            'description' => 'Telegram user IDs allowed to use the operations bot (comma-separated)',
        ]);
    }
}
