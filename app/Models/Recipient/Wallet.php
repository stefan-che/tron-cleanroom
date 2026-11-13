<?php

namespace App\Models\Recipient;

use App\Models\BaseModel;
use App\Observers\RecipientWalletObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property integer $id
 * @property integer $recipient_id
 * @property int $type
 * @property int $blockchain_type
 * @property string $address
 *
 * @property string $type_name
 * @property string $type_name_en
 * @property string $blockchain_type_name
 * @property string $tronscan_address_link
 * @property string $regexp
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 *
 * @property Recipient $recipient
 */
class Wallet extends BaseModel
{
    use AsSource, Filterable, SoftDeletes;

    /** Transient: initiator of the change; not stored in the DB, read in RecipientWalletObserver */
    public string $changedBy = 'system';
    public int $changedById = 0;

    protected static function booted(): void
    {
        static::observe(RecipientWalletObserver::class);
    }

    public const int BLOCKCHAIN_TYPE_TRC_20 = 1;
    public const int BLOCKCHAIN_TYPE_BTC = 2;
    public const int BLOCKCHAIN_TYPE_ETH = 3;

    public const int TYPE_WHITE = 1;
    public const int TYPE_GRAY = 2;
    public const int TYPE_BLACK = 3;
    public const int TYPE_YELLOW = 4;

    protected $table = 'recipient_wallets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'recipient_id',
        'response',
        'type',
        'blockchain_type',
        'address',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    public static function getTypeByNameEng(string $type): ?int
    {
        $types = array_flip(self::typesEng());
        return $types[strtolower($type)] ?? null;
    }

    public function recipient()
    {
        return $this->belongsTo(Recipient::class);
    }


    public function scopeByType(Builder $query, int $type)
    {
        return $query->where('type', $type);
    }

    public function scopeTypeWhite(Builder $query)
    {
        return $query->byType(self::TYPE_WHITE);
    }

    public function scopeTypeGray(Builder $query)
    {
        return $query->byType(self::TYPE_GRAY);
    }

    public function scopeTypeYellow(Builder $query)
    {
        return $query->byType(self::TYPE_YELLOW);
    }

    /**
     * type_name
     * @return string
     */
    public function getTypeNameAttribute(): string
    {
        return self::allTypes()[$this->type] ?? '-';
    }

    /**
     * type_name_en
     * @return string
     */
    public function getTypeNameEnAttribute(): string
    {
        return self::typesEng()[$this->type] ?? '-';
    }

    /**
     * blockchain_type_name
     * @return string
     */
    public function getBlockchainTypeNameAttribute(): string
    {
        return self::blockChainTypesNames()[$this->blockchain_type] ?? '-';
    }

    /**
     * regexp
     * @return string
     */
    public function getRegexpAttribute(): string
    {
        return self::blockChainTypesRegexp()[$this->blockchain_type] ?? '';
    }

    public static function allTypes(): array
    {
        return [
            self::TYPE_WHITE => 'White',
            self::TYPE_GRAY => 'Gray',
            self::TYPE_YELLOW => 'Yellow',
            self::TYPE_BLACK => 'Black',
        ];
    }

    public static function getTypesWithoutBlack(): array
    {
        $types = self::allTypes();

        unset($types[self::TYPE_BLACK]);

        return $types;
    }

    public static function typesIcons(): array
    {
        return [
            self::TYPE_WHITE => '🟢',
            self::TYPE_GRAY => '⚪️',
            self::TYPE_YELLOW => '🟡',
            self::TYPE_BLACK => '⚫️',
        ];
    }

    public static function typesEng(): array
    {
        return [
            self::TYPE_WHITE => 'white',
            self::TYPE_GRAY => 'gray',
            self::TYPE_YELLOW => 'yellow',
            self::TYPE_BLACK => 'black',
        ];
    }

    public static function blockChainTypesNames(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => 'trc-20',
            self::BLOCKCHAIN_TYPE_BTC => 'btc',
            self::BLOCKCHAIN_TYPE_ETH => 'eth',
        ];
    }

    public static function blockChainTypeTrc20Names(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => 'trc-20',
        ];
    }

    public static function blockChainTypesRegexp(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
            self::BLOCKCHAIN_TYPE_BTC => '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$|^(bc1)[0-9A-Za-z]{25,39}$/',
            self::BLOCKCHAIN_TYPE_ETH => '/^0x[a-fA-F0-9]{40}$/',
        ];
    }

    /**
     * tronscan_address_link
     * @return string
     */
    public function getTronscanAddressLinkAttribute(): string
    {
        return $this->address ? ('https://tronscan.org/#/address/' . $this->address) : '';
    }


    public function isTypeWhite(): bool
    {
        return $this->type === self::TYPE_WHITE;
    }

    public function isTypeGray(): bool
    {
        return $this->type === self::TYPE_GRAY;
    }

    public function isTypeBlack(): bool
    {
        return $this->type === self::TYPE_BLACK;
    }

    public function isTypeYellow(): bool
    {
        return $this->type === self::TYPE_YELLOW;
    }

}
