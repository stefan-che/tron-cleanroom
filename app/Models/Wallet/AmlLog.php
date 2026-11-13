<?php

namespace App\Models\Wallet;

use App\Models\Aml\AmlService;
use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property integer $id
 * @property integer $transaction_id
 * @property integer $wallet_id
 * @property integer $aml_service_id
 * @property float $aml_balance
 * @property string|array $response
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 *
 * @method amlBot
 */
class AmlLog extends BaseModel
{
    public const float MIN_RISC_SCORE = 0.65;

    protected $table = 'wallets_aml_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'aml_service_id',
        'response',
        'aml_balance',
    ];

    /**
     * @return BelongsTo
     */
    public function amlService(): BelongsTo
    {
        return $this->belongsTo(AmlService::class, 'aml_service_id');
    }

    public function scopeAmlBot(Builder $query)
    {
        return $query->where('aml_service_id', 1);
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'response' => 'array',
    ];

}
