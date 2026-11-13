<?php

namespace App\Models\Recipient;

use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property integer $id
 * @property string $external_id Public recipient ID for the API (recipient_id); generated if not set
 * @property string $name
 * @property string $comment
 * @property string|null $ledger_user_id User ID in the ledger (GET /api/accounts?user_id=); empty means the integration is disabled for this recipient
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 *
 * @property Wallet[] $wallets
 * @property TgUser[] $tgUsers
 * @property LedgerAccount[] $ledgerAccounts
 */
class Recipient extends BaseModel
{

    use SoftDeletes, AsSource, Filterable;

    protected $table = 'recipients';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'comment',
        'external_id',
        'ledger_user_id',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    protected static function booted(): void
    {
        static::creating(function (Recipient $recipient) {
            if (empty($recipient->external_id)) {
                $recipient->external_id = (string) Str::uuid();
            }
        });
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function tgUsers(): BelongsToMany
    {
        return $this->belongsToMany(TgUser::class, 'recipient_tg_user');
    }

    // Only accounts enabled for display in the bot
    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class)->where('is_show_bot', true);
    }

}
