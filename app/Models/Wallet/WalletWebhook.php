<?php

namespace App\Models\Wallet;

use App\Models\BaseModel;
use Carbon\Carbon;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $network
 * @property string $token
 * @property string $txid
 * @property int $block
 * @property string $from_address
 * @property string $to_address
 * @property string $amount
 * @property string $amount_raw
 * @property string $direction
 * @property Carbon|null $event_ts
 * @property array $payload
 * @property string $ip_address
 * @property Carbon $created_at
 */
class WalletWebhook extends BaseModel
{
    use AsSource;

    protected $table = 'wallet_webhooks';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload'  => 'array',
        'event_ts' => 'datetime',
        'created_at' => 'datetime',
    ];
}
