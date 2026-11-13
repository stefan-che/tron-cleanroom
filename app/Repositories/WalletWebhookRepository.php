<?php

namespace App\Repositories;

use App\Models\Wallet\Wallet;
use App\Models\Wallet\WalletWebhook;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class WalletWebhookRepository extends Repository
{
    protected function query(): Builder
    {
        return WalletWebhook::query();
    }

    public function createFromWebhookData(Wallet $wallet, array $data, string $ipAddress): WalletWebhook
    {
        return WalletWebhook::create([
            'wallet_id' => $wallet->id,
            'network' => $data['network'] ?? null,
            'token' => $data['token'] ?? null,
            'txid' => $data['txid'] ?? null,
            'block' => $data['block'] ?? null,
            'from_address' => $data['from'] ?? null,
            'to_address' => $data['to'] ?? null,
            'amount' => $data['amount'] ?? null,
            'amount_raw' => $data['amount_raw'] ?? null,
            'direction' => $data['direction'] ?? null,
            'event_ts' => isset($data['ts']) ? Carbon::parse($data['ts']) : null,
            'payload' => $data,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
