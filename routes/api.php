<?php

use \App\Http\Controllers\WalletController;
use \App\Http\Controllers\AmlController;
use \App\Http\Controllers\TransactionController;
use \App\Http\Controllers\EnergyWalletController;
use \App\Http\Controllers\RecipientController;
use \App\Http\Controllers\LedgerAccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//api/wallet/trc-20/generate
//api/wallet/status/{address}
//api/wallet/send/{address}/white
//api/wallet/send/{address}/gray
//api/wallet/send/{address}/black?to-address=
//api/wallet/send/{address}/{type}?amount= - amount is optional: if given, only that amount is sent, otherwise the whole balance
//api/wallet/activate/{address}
//api/wallet/address-to/{address} - change the destination address (sell only)

Route::controller(WalletController::class)->prefix('wallet')
    ->middleware(['api.access_ip'])
    ->group(function() {

    Route::get('/list', 'getList')
        ->middleware('api.key.readonly')
        ->name('api.wallet.list');

    Route::post('/{blockchain}/generate', 'generate')
        ->middleware('api.key')
        ->name('api.wallet.generate');

    Route::get('/status/{address}', 'status')
        ->middleware('api.key.readonly')
        ->name('api.wallet.status');

    Route::post('/send/{address}/{type}', 'send')
        ->middleware('api.key')
        ->name('api.wallet.send');

    Route::post('/activate/{address}', 'activate')
        ->middleware('api.key')
        ->name('api.wallet.activate');

    Route::post('/address-to/{address}', 'changeAddressTo')
        ->middleware('api.key')
        ->name('api.wallet.address-to');
});

//api/aml/{blockchain}/{address} - api/aml/trc-20/{address}
//api/aml/status/{address}
//api/aml/status/{blockchain}/{address}

Route::controller(AmlController::class)->prefix('aml')
    ->middleware(['api.access_ip'])
    ->group(function() {
    Route::post('/{blockchain}/{address}', 'check')
        ->middleware('api.key')
        ->name('api.aml.check');

    Route::get('/status/{blockchain}/{address}', 'status')
        ->middleware('api.key.readonly')
        ->name('api.aml.blockchain_status');

    Route::get('/status/{address}', 'status')
        ->middleware('api.key.readonly')
        ->name('api.aml.status');

});

//api/recipients
//api/recipients/{id}

Route::controller(RecipientController::class)->prefix('recipients')
    ->middleware(['api.key.readonly', 'api.access_ip'])
    ->group(function () {
    Route::get('/', 'index')
        ->name('api.recipients.index');

    Route::get('/{identifier}', 'show')
        ->name('api.recipients.show');
});

//api/energy-wallets
//api/energy-wallets?search=T...&per_page=30&only_enabled=true
//api/energy-wallets/{id}

Route::controller(EnergyWalletController::class)->prefix('energy-wallets')
    ->middleware(['api.key.readonly', 'api.access_ip'])
    ->group(function () {
    Route::get('/', 'index')
        ->name('api.energy-wallets.index');

    Route::get('/{id}', 'show')
        ->name('api.energy-wallets.show');
});

//api/ledger-accounts/{ledgerId}/wallet - optional ledger integration; responds 503 without LEDGER_API_*

Route::controller(LedgerAccountController::class)->prefix('ledger-accounts')
    ->middleware(['api.key', 'api.access_ip'])
    ->group(function () {
    Route::post('/{ledgerId}/wallet', 'linkWallet')
        ->name('api.ledger-accounts.wallet.link');

    Route::delete('/{ledgerId}/wallet/{ledgerWalletId}', 'unlinkWallet')
        ->name('api.ledger-accounts.wallet.unlink');
});

//api/transaction/{hash}/run-aml
Route::controller(TransactionController::class)
    ->middleware(['api.key', 'api.access_ip'])
    ->prefix('transaction')->group(function() {
    Route::post('/{hash}/run-aml', 'runAml')
        ->name('api.transaction.runaml');
});
