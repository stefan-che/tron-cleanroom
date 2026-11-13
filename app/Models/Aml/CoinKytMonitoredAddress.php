<?php

namespace App\Models\Aml;

use App\Models\BaseModel;
use App\Models\Wallet\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

/**
 * Local registry of addresses placed under CoinKYT monitoring.
 *
 * @property int $id
 * @property ?int $wallet_id
 * @property string $blockchain
 * @property string $address
 * @property ?string $token
 * @property ?int $risk_profile_id
 * @property ?string $risk_profile
 * @property bool $address_changes
 * @property int $status
 * @property ?Carbon $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ?Wallet $wallet
 */
class CoinKytMonitoredAddress extends BaseModel
{
    use AsSource;

    const int STATUS_REMOVED = 0;
    const int STATUS_ACTIVE = 1;

    protected $table = 'coinkyt_monitored_addresses';

    protected $guarded = [];

    protected $casts = [
        'address_changes' => 'boolean',
        'status'          => 'integer',
        'last_synced_at'  => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CoinKytMonitoringEvent::class, 'monitored_address_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
