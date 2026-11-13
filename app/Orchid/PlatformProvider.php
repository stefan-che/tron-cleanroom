<?php

declare(strict_types=1);

namespace App\Orchid;

use App\Models\Role;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Dashboard $dashboard
     *
     * @return void
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Operations')
                ->icon('money')
                ->rawClick()
                ->permission(Role::PERMISSION_OPERATIONS)
                ->route('platform.operations.list'),

            Menu::make('Statistics')
                ->icon('money')
                ->rawClick()
                ->permission(Role::PERMISSION_OPERATIONS)
                ->route('platform.operations.statistics'),

            Menu::make('AML')
                ->icon('money')
                ->rawClick()
                ->permission(Role::PERMISSION_AML)
                ->route('platform.aml.list')
                ->list([
                    Menu::make('AML database export')
                        ->icon('bs.box-arrow-up-right')
                        ->rawClick()
                        ->permission(Role::PERMISSION_AML)
                        ->route('platform.aml.export'),
                ]),

            Menu::make('Reports')
                ->icon('bs.file-earmark-spreadsheet')
                ->rawClick()
                ->permission(Role::PERMISSION_AML)
                ->route('platform.reports.ai-aml')
                ->list([
                    Menu::make('AI AML checks')
                        ->icon('bs.robot')
                        ->rawClick()
                        ->permission(Role::PERMISSION_AML)
                        ->route('platform.reports.ai-aml'),
                ]),

            Menu::make('Recipients')
                ->icon('orc.pointer')
                ->rawClick()
                ->permission(Role::PERMISSION_RECIPIENTS)
                ->route('platform.recipients.list')
                ->list([
                    Menu::make('Users')
                        ->icon('bs.person-lines-fill')
                        ->rawClick()
                        ->permission(Role::PERMISSION_RECIPIENTS)
                        ->route('platform.tg_users.list'),
                ]),


            Menu::make('TRX wallets')
                ->icon('bs.card-text')
                ->rawClick()
                ->permission(Role::PERMISSION_TRX_WALLETS)
                ->route('platform.wallet_trx.list'),

            Menu::make('Energy auto-replenishment')
                ->icon('bs.lightning-charge')
                ->rawClick()
                ->permission(Role::PERMISSION_ENERGY_WALLET_POOL)
                ->route('platform.energy.auto-replenishment'),

            Menu::make('Security')
                ->icon('bs.shield-lock')
                ->rawClick()
                ->permission(Role::PERMISSION_SECURITY_BLOCKED_IPS)
                ->route('platform.security.blocked-ips.list'),

            Menu::make('Settings')
                ->icon('wrench')
                ->rawClick()
                ->permission(Role::PERMISSION_SETTINGS)
                ->route('platform.settings.list')
                ->divider(),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->rawClick()
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Access Controls')),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->rawClick()
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles')
                ->divider(),
        ];
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),
        ];
    }
}
