<?php
/**
 * php artisan db:seed --class=AmlServicesSeeder
 */

namespace Database\Seeders;

use App\Models\Aml\AmlAddressCheck;
use App\Models\Aml\AmlService;
use Illuminate\Database\Seeder;

class AmlServicesSeeder extends Seeder
{
    public function run()
    {
        $services = [
            [
                'name' => 'AML Bot',
                'code' => AmlService::AML_BOT_CODE,
                'site_link' => 'https://amlbot.com',
                'api_doc_link' => 'https://docs.amlbot.com',
                'is_enabled' => true,
            ],
            [
                'name' => 'WellWallet',
                'code' => AmlService::WELL_WALLET_CODE,
                'site_link' => 'https://t.me/well_walletbot',
                'api_doc_link' => 'https://docs.wellwallet.io',
                'is_enabled' => false,
            ],
//            [
//                'name' => 'Elliptic',
//                'code' => AmlService::ELLIPTIC_CODE,
//                'site_link' => 'https://www.elliptic.co',
//                'api_doc_link' => 'https://docs.elliptic.co',
//                'is_enabled' => false,
//            ],
            [
                'name' => 'GetBlock',
                'code' => AmlService::GETBLOCK_CODE,
                'site_link' => 'https://getblock.net',
                'api_doc_link' => 'https://getblock.net/api/documentation',
                'is_enabled' => false,
            ],
            [
                'name' => 'BitOK AML',
                'code' => AmlService::AML_BITOK_CODE,
                'site_link' => 'https://kyt-api.bitok.org',
                'api_doc_link' => 'https://kyt-api.bitok.org/swagger/',
                'is_enabled' => false,
            ],
            [
                'name' => 'CoinKYT',
                'code' => AmlService::COINKYT_CODE,
                'site_link' => 'https://explorer.coinkyt.com/ru/explorer',
                'api_doc_link' => '',
                'is_enabled' => false,
            ],
        ];

        foreach ($services as $service) {
            AmlService::firstOrCreate(
                ['code' => $service['code']],
                $service
            );
        }

        AmlAddressCheck::query()
            ->whereNull('aml_service_id')
            ->update([
                'aml_service_id' => 1,
            ]);

    }
}








