<?php
/**
 * php artisan db:seed --class=UserRoleSeeder
 */
namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::firstOrCreate(['slug' => Role::ROLE_NAME_MANAGER], [
            'name' => 'Manager',
            'permissions' => [
                'platform.index' => '1',
                'platform.aml.list' => '1',
                'platform.recipients.list' => '1',
                'platform.settings.list' => '1',
                'platform.systems.roles' => '0',
                'platform.systems.users' => '1',
                'platform.operations.list' => '1',
                'platform.trx_wallets.list' => '1',
                'platform.systems.attachment' => '1',
                'platform.operations.set_wallet' => '1',
            ],
        ]);

        Role::firstOrCreate(['slug' => Role::ROLE_NAME_ADMIN], [
            'name' => 'Administrator',
            'permissions' =>  [
                'platform.index' => '1',
                'platform.aml.list' => '1',
                'platform.recipients.list' => '1',
                'platform.settings.list' => '1',
                'platform.systems.roles' => '1',
                'platform.systems.users' => '1',
                'platform.operations.list' => '1',
                'platform.trx_wallets.list' => '1',
                'platform.systems.attachment' => '1',
                'platform.operations.set_wallet' => '1',
            ],
        ]);

        Role::firstOrCreate(['slug' => Role::ROLE_NAME_USER], [
            'name' => 'User',
            'permissions' =>  [
                'platform.index' => '1',
                'platform.recipients.list' => '0',
                'platform.settings.list' => '0',
                'platform.systems.roles' => '0',
                'platform.systems.users' => '0',
                'platform.operations.list' => '1',
                'platform.trx_wallets.list' => '1',
                'platform.systems.attachment' => '1',
            ],
        ]);
    }
}
