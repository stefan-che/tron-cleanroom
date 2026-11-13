<?php

namespace App\Models\Energy;

use App\Enums\Energy\EnergyWalletEvent;
use App\Models\BaseModel;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Observers\Energy\EnergyWalletObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property string $address
 * @property string $label
 * @property int $recipient_wallet_id
 * @property bool $auto_replenishment_enabled
 * @property int|null $energy_service_id  Service used to perform the smart start
 * @property Carbon $expires_at
 * @property int $telegram_chat_id
 * @property int $recipient_id
 * @property bool $is_error  true if the last API call failed after all retries
 *
 * @property RecipientWallet $recipientWallet
 * @property Recipient $recipient
 * @property string $tronscan_address_link
 * @property EnergyService|null $energyService
 * @property EnergyWalletHistory[] $history
 */
class EnergyWallet extends BaseModel
{
    use HasFactory, SoftDeletes, AsSource, Filterable;

    protected static function booted(): void
    {
        static::observe(EnergyWalletObserver::class);
    }

    /** Default number of days a new wallet gets before auto-disable */
    const int DEFAULT_EXPIRES_DAYS = 8;

    protected $table = 'energy_wallets';

    protected $fillable = [
        'recipient_wallet_id',
        'address',
        'label',
        'auto_replenishment_enabled',
        'expires_at',
        'energy_service_id',
        'is_error',
        'telegram_chat_id',
        'recipient_id',
    ];

    protected $casts = [
        'auto_replenishment_enabled' => 'boolean',
        'expires_at' => 'datetime',
        'is_error' => 'boolean',
    ];

    public function recipientWallet(): BelongsTo
    {
        return $this->belongsTo(RecipientWallet::class, 'recipient_wallet_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'recipient_id');
    }

    public function energyService(): BelongsTo
    {
        return $this->belongsTo(EnergyService::class, 'energy_service_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EnergyWalletHistory::class, 'energy_wallets_id');
    }

    public function addHistory(EnergyWalletEvent $event, string $changedBy = 'admin', ?string $note = null, ?array $apiResponse = null): void
    {
        $this->history()->create([
            'event' => $event->value,
            'changed_by' => $changedBy,
            'note' => $note,
            'api_response' => $apiResponse ? json_encode($apiResponse, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            // service_id: for SMART_START take it from the API response (not yet saved on the wallet),
            // for all other events, from the wallet
            'energy_service_id' => $apiResponse['service_id'] ?? $this->energy_service_id,
        ]);
    }

    /**
     * tronscan_address_link
     * @return string
     */
    public function getTronscanAddressLinkAttribute(): string
    {
        return $this->address ? ('https://tronscan.org/#/address/' . $this->address) : '';
    }
}
