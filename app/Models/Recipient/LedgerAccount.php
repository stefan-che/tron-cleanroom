<?php

namespace App\Models\Recipient;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * Account in the external ledger; a mirror used to link recipient wallets.
 *
 * @property int $id
 * @property string $ledger_id
 * @property string $group_id
 * @property int $recipient_id
 * @property string $user_id
 * @property string $name
 * @property string $currency
 * @property int $amount
 * @property int|null $wallet_id
 * @property bool $is_show_bot
 *
 * @property Recipient $recipient
 * @property Wallet|null $wallet
 */
class LedgerAccount extends BaseModel
{
    use AsSource, Filterable;

    protected $table = 'ledger_accounts';

    protected $fillable = [
        'ledger_id',
        'group_id',
        'recipient_id',
        'user_id',
        'name',
        'currency',
        'amount',
        'wallet_id',
        'is_show_bot',
    ];

    protected $casts = [
        'is_show_bot' => 'boolean',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function ledgerWallets(): HasMany
    {
        return $this->hasMany(LedgerAccountWallet::class, 'ledger_account_id');
    }
}
