<?php

use App\Http\Controllers\AmlController;
use App\Http\Controllers\AmlReportController;
use App\Http\Controllers\Orchid\LoginController;
use App\Http\Controllers\Telegram\OperationsTelegramController;
use App\Http\Controllers\Telegram\TelegramController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use App\Http\Middleware\Telegram\TgSecretTokenCheck;
use App\Http\Middleware\Telegram\TgOperationBotSecretTokenCheck;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook'])
    ->middleware([TgSecretTokenCheck::class])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('telegram.webhook');

Route::post('/telegram/operations/webhook', [OperationsTelegramController::class, 'handleWebhook'])
    ->middleware([TgOperationBotSecretTokenCheck::class])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('telegram.operations.webhook');

Route::post(config('platform.prefix') . '/admin-login', [LoginController::class, 'login'])
    ->name('admin.login');

Route::controller(WalletController::class)->prefix('wallet')->group(function () {
    Route::get('/report/{address}', 'report')
        ->name('wallet.report');
});

Route::controller(AmlController::class)->prefix('aml')->group(function () {
    Route::get('/report/{address}', 'report')
        ->name('aml.report');
});

// Path is configurable (AML_WEBHOOK_PATH): use a hard-to-guess value in production.
Route::post(config('services.aml.webhook_path'), [AmlController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('aml.webhook');

Route::controller(TransactionController::class)
    ->prefix('transaction')->group(function () {
        Route::get('/report/{id}', 'report')
            ->name('transaction.report');
    });

Route::controller(AmlReportController::class)->group(function () {
    Route::get('/aml-report/address/{checkId}', 'downloadAddressReport')
        ->name('aml.report.address');

    Route::get('/aml-report/transaction/{checkId}', 'downloadTransactionReport')
        ->name('aml.report.transaction');
});

require __DIR__ . '/webhook.php';
