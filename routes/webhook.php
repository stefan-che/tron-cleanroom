<?php

use App\Http\Controllers\WalletWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::post('/webhook/wallet-listener', [WalletWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhook.wallet-listener');
