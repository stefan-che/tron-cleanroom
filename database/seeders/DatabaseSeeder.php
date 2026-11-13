<?php

/**
 * php artisan db:seed
 */

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);
        $this->call(UserRoleSeeder::class);
        $this->call(EnergyServicesSeeder::class);
        $this->call(AmlServicesSeeder::class);
        $this->call(AiServicesSeeder::class);
        $this->call(TronNodesSeeder::class);
    }
}
