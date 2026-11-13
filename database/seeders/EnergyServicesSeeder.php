<?php
/**
 * php artisan db:seed --class=EnergyServicesSeeder
 */

namespace Database\Seeders;

use App\Models\Energy\EnergyService;
use Illuminate\Database\Seeder;

class EnergyServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'ReFee',
                'code' => EnergyService::REFEE_CODE,
                'description' => '',
                'is_enabled' => true,
                'priority' => 1,
                'site_link' => 'https://refee.bot/',
                'api_doc_link' => 'https://api.refee.bot/v2/docs',
            ],
            [
                'name' => 'Tr.energy',
                'code' => EnergyService::TR_ENERGY_CODE,
                'description' => '',
                'is_enabled' => false,
                'priority' => 2,
                'site_link' => 'https://tr.energy/ru/',
                'api_doc_link' => 'https://documenter.getpostman.com/view/7317157/2s9YRGw8qy',
            ],
            [
                'name' => 'Netts.io',
                'code' => EnergyService::NETTS_CODE,
                'description' => '',
                'is_enabled' => false,
                'priority' => 3,
                'site_link' => 'https://www.netts.io/',
                'api_doc_link' => 'https://doc.netts.io/api/v2/endpoints/order1h.html#endpoint-url',
            ],
        ];

        foreach ($services as $service) {
            EnergyService::updateOrCreate(
                ['code' => $service['code']],
                $service
            );
        }
    }
}

