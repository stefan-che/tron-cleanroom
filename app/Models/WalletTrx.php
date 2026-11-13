<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property integer $id
 * @property float $amount
 * @property string $address
 * @property string $private_key
 * @property string $comment
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 *
 * @property string $tronscan_address_link
 *
 */
class WalletTrx extends BaseModel
{
    use SoftDeletes, Filterable, AsSource;

    protected $table = 'wallets_trx';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'address',
        'private_key',
        'comment',
        'amount',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];



    /**
     * tronscan_address_link
     * @return string
     */
    public function getTronscanAddressLinkAttribute(): string
    {
        return $this->address ? ('https://tronscan.org/#/address/' . $this->address) : '';
    }

}
