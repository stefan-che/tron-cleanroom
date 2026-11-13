<?php

declare(strict_types=1);

use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\Settings\SettingsScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;
use \App\Orchid\Screens\WalletsTrx\WalletTrxEditScreen;
use \App\Orchid\Screens\WalletsTrx\WalletsTrxListScreen;
use \App\Orchid\Screens\Operations\WalletInfoScreen;
use \App\Orchid\Screens\Operations\WalletsListScreen;
use \App\Orchid\Screens\Operations\WalletsStatisticsScreen;
use \App\Orchid\Screens\Aml\AmlListScreen;
use \App\Orchid\Screens\Aml\AmlInfoScreen;
use \App\Orchid\Screens\Aml\AmlExportScreen;
use \App\Orchid\Screens\Security\BlockedIpsListScreen;
use \App\Orchid\Screens\Security\BlockedIpEditScreen;
use \App\Orchid\Screens\Energy\AutoReplenishmentScreen;
use \App\Orchid\Screens\Energy\EnergyWalletInfoScreen;



/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/


Route::screen('settings', SettingsScreen::class)
    ->name('platform.settings.list');

Route::screen('energy/auto-replenishment', AutoReplenishmentScreen::class)
    ->name('platform.energy.auto-replenishment');

Route::screen('energy/wallet/{wallet}/info', EnergyWalletInfoScreen::class)
    ->name('platform.energy.wallet.info');

Route::screen('wallet-trx/{wallet}/edit', WalletTrxEditScreen::class)
    ->name('platform.wallet_trx.edit');

Route::screen('wallet-trx', WalletsTrxListScreen::class)
    ->name('platform.wallet_trx.list');

Route::screen('operations', WalletsListScreen::class)
    ->name('platform.operations.list');


Route::screen('operations/{wallet}/info', WalletInfoScreen::class)
    ->name('platform.operation.info');

Route::screen('operations/statistics', WalletsStatisticsScreen::class)
    ->name('platform.operations.statistics');

Route::screen('aml', AmlListScreen::class)
    ->name('platform.aml.list');

Route::screen('aml/export', AmlExportScreen::class)
    ->name('platform.aml.export');

Route::screen('aml/{aml}/info', AmlInfoScreen::class)
    ->name('platform.aml.info');

Route::screen('reports/ai-aml', \App\Orchid\Screens\Reports\AiAmlReportScreen::class)
    ->name('platform.reports.ai-aml');

//Route::screen('operation/{operation}/logs', WalletInfoScreen::class)
//    ->name('platform.operation.info');

Route::screen('recipients/create', \App\Orchid\Screens\Recipients\RecipientEditScreen::class)
    ->name('platform.recipient.create');

Route::screen('recipients/{recipient}/edit', \App\Orchid\Screens\Recipients\RecipientEditScreen::class)
    ->name('platform.recipient.edit');

Route::screen('recipients/{recipient}/wallets', \App\Orchid\Screens\Recipients\RecipientsWalletsScreen::class)
    ->name('platform.recipient.wallets');

Route::screen('recipients/{recipient}/tg-users', \App\Orchid\Screens\Recipients\RecipientsTgUsersScreen::class)
    ->name('platform.recipient.tg_users');

Route::screen('recipients/{recipient}/accounts', \App\Orchid\Screens\Recipients\RecipientAccountsScreen::class)
    ->name('platform.recipient.accounts');

Route::screen('tg-users', \App\Orchid\Screens\TgUsers\TgUsersListScreen::class)
    ->name('platform.tg_users.list');

Route::screen('tg-users/create', \App\Orchid\Screens\TgUsers\TgUserEditScreen::class)
    ->name('platform.tg_users.create');

Route::screen('tg-users/{tgUser}/edit', \App\Orchid\Screens\TgUsers\TgUserEditScreen::class)
    ->name('platform.tg_users.edit');

Route::screen('recipients', \App\Orchid\Screens\Recipients\RecipientsListScreen::class)
    ->name('platform.recipients.list');



// Main
Route::screen('/main', PlatformScreen::class)
    ->name('platform.main');

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));

// Security > Blocked IPs
Route::screen('security/blocked-ips', BlockedIpsListScreen::class)
    ->name('platform.security.blocked-ips.list')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Blocked IPs', route('platform.security.blocked-ips.list')));

Route::screen('security/blocked-ips/create', BlockedIpEditScreen::class)
    ->name('platform.security.blocked-ips.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.security.blocked-ips.list')
        ->push('Create block', route('platform.security.blocked-ips.create')));

Route::screen('security/blocked-ips/{blockedIp}/edit', BlockedIpEditScreen::class)
    ->name('platform.security.blocked-ips.edit')
    ->breadcrumbs(fn (Trail $trail, $blockedIp) => $trail
        ->parent('platform.security.blocked-ips.list')
        ->push('Edit block', route('platform.security.blocked-ips.edit', $blockedIp)));

//Route::screen('idea', Idea::class, 'platform.screens.idea');
