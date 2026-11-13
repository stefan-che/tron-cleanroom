<?php

namespace App\Models\Recipient;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

/**
 * Wallet linked to a ledger account.
 *
 * @property int $id
 * @property int $ledger_account_id
 * @property string $ledger_wallet_id
 * @property string $wallet_address
 * @property string $network
 *
 * @property LedgerAccount $account
 */
class LedgerAccountWallet extends BaseModel
{
    use AsSource;

    public const string NETWORK_TRON = 'tron';

    protected $table = 'ledger_account_wallets';

    protected $fillable = [
        'ledger_account_id',
        'ledger_wallet_id',
        'wallet_address',
        'network',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
