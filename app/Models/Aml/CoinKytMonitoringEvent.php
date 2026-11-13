<?php

namespace App\Models\Aml;

use App\Models\BaseModel;
use App\Models\Wallet\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

/**
 * CoinKYT monitoring event/notification (6.1).
 *
 * @property int $id
 * @property string $external_id
 * @property ?int $monitored_address_id
 * @property ?int $wallet_id
 * @property string $tracked_address
 * @property ?string $blockchain
 * @property ?string $token
 * @property ?string $risk_profile
 * @property ?string $risk_grade
 * @property ?string $balance
 * @property ?string $sender
 * @property ?string $txid
 * @property ?array $triggered_by
 * @property ?array $address_changes
 * @property ?array $raw
 * @property ?Carbon $event_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ?Wallet $wallet
 * @property ?CoinKytMonitoredAddress $monitoredAddress
 */
class CoinKytMonitoringEvent extends BaseModel
{
    use AsSource;

    const string GRADE_HIGH = 'high';
    const string GRADE_MODERATE = 'moderate';
    const string GRADE_LOW = 'low';
    const string GRADE_UNDEFINED = 'undefined';

    protected $table = 'coinkyt_monitoring_events';

    protected $guarded = [];

    protected $casts = [
        'triggered_by'    => 'array',
        'address_changes' => 'array',
        'raw'             => 'array',
        'event_at'        => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function monitoredAddress(): BelongsTo
    {
        return $this->belongsTo(CoinKytMonitoredAddress::class, 'monitored_address_id');
    }
}
