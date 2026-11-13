<?php

namespace App\Models\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

/**
 * @property string $note
 * @property string $event
 * @property int $energy_wallets_id
 * @property int|null $energy_service_id
 * @property string|array $api_response
 * @property Carbon $created_at
 *
 * @property EnergyWallet $walletPool
 * @property EnergyService|null $energyService
 * @property string $event_label
 */
class EnergyWalletHistory extends BaseModel
{
    use AsSource;

    public $timestamps = false;

    protected $table = 'energy_wallets_history';

    protected $fillable = [
        'energy_wallets_id',
        'event',
        'changed_by',
        'note',
        'api_response',
        'energy_service_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'api_response' => 'array',
    ];

    public function walletPool(): BelongsTo
    {
        // withTrashed so that history of deleted wallets is shown too
        return $this->belongsTo(EnergyWallet::class, 'energy_wallets_id')->withTrashed();
    }

    public function energyService(): BelongsTo
    {
        return $this->belongsTo(EnergyService::class, 'energy_service_id');
    }

    public function getEventLabelAttribute(): string
    {
        $case = EnergyWalletEvent::tryFrom($this->event);
        return $case?->label() ?? $this->event;
    }
}
