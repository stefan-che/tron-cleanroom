<?php

namespace App\Providers;

use App\Models\Role;
use Illuminate\Support\ServiceProvider;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\Dashboard;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * @param Dashboard $dashboard
     */
    public function boot(Dashboard $dashboard)
    {
        $permissions = ItemPermission::group('Settings')
            ->addPermission(Role::PERMISSION_SETTINGS, 'Settings access')
            ->addPermission(Role::PERMISSION_SECURITY_BLOCKED_IPS, 'Security access');
        $dashboard->registerPermissions($permissions);

        $permissions = ItemPermission::group('Recipients')
            ->addPermission(Role::PERMISSION_RECIPIENTS, 'Section access');
        $dashboard->registerPermissions($permissions);

        $permissions = ItemPermission::group('Operations')
            ->addPermission(Role::PERMISSION_OPERATIONS, 'Section access')
            ->addPermission(Role::PERMISSION_OPERATIONS_SET_WALLET, 'Sweep to recipient wallets')
            ->addPermission(Role::PERMISSION_OPERATIONS_SET_CUSTOM_WALLET, 'Sweep to custom wallets')
            ->addPermission(Role::PERMISSION_TESTING, 'Testing operations');
        $dashboard->registerPermissions($permissions);


        $permissions = ItemPermission::group('AML checks')
            ->addPermission(Role::PERMISSION_AML, 'AML');
        $dashboard->registerPermissions($permissions);

        $permissions = ItemPermission::group('TRX wallets')
            ->addPermission(Role::PERMISSION_TRX_WALLETS, 'Section access');
        $dashboard->registerPermissions($permissions);

        $permissions = ItemPermission::group('Energy auto-replenishment')
            ->addPermission(Role::PERMISSION_ENERGY_WALLET_POOL, 'Section access');
        $dashboard->registerPermissions($permissions);
    }
}
