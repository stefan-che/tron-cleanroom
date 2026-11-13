<?php

namespace App\Models\Recipient;

use App\Models\BaseModel;
use Carbon\Carbon;

/**
 * @property integer $id
 * @property integer $wallet_id
 * @property integer $action
 * @property string $payload
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Log extends BaseModel
{

    protected $table = 'recipient_wallets_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wallet_id',
        'action',
        'payload',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'payload' => 'array',
    ];

}
