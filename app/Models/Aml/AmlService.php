<?php

namespace App\Models\Aml;

use App\Models\BaseModel;
use App\Models\Wallet\TransactionAmlCheck;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $site_link
 * @property string $api_doc_link
 * @property bool $is_enabled
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 *
 * @property TransactionAmlCheck[] $transactionChecks
 * @property AmlAddressCheck[] $addressChecks
 * @method amlBot
 * @method getBlock
 * @method wellWallet
 * @method amlBitOk
 * @method coinKyt
 */
class AmlService extends BaseModel
{
    use HasFactory, AsSource, Filterable, SoftDeletes;

    const string WELL_WALLET_CODE = 'wellwallet';
    const string AML_BOT_CODE = 'amlbot';
    const string ELLIPTIC_CODE = 'elliptic';
    const string GETBLOCK_CODE = 'getblock';
    const string AML_BITOK_CODE = 'amlbitok';
    const string COINKYT_CODE = 'coinkyt';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * @return HasMany
     */
    public function transactionChecks(): HasMany
    {
        return $this->hasMany(TransactionAmlCheck::class, 'service_id');
    }

    /**
     * @return HasMany
     */
    public function addressChecks(): HasMany
    {
        return $this->hasMany(AmlAddressCheck::class, 'aml_service_id');
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function scopeWellWallet(Builder $query)
    {
        return $query->where('code', self::WELL_WALLET_CODE);
    }

    public function scopeAmlBot(Builder $query)
    {
        return $query->where('code', self::AML_BOT_CODE);
    }

    public function scopeElliptic(Builder $query)
    {
        return $query->where('code', self::ELLIPTIC_CODE);
    }

    public function scopeGetBlock(Builder $query)
    {
        return $query->where('code', self::GETBLOCK_CODE);
    }

    public function scopeAmlBitOk(Builder $query)
    {
        return $query->where('code', self::AML_BITOK_CODE);
    }

    public function scopeCoinKyt(Builder $query)
    {
        return $query->where('code', self::COINKYT_CODE);
    }
}








