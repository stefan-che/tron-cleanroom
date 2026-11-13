<?php

namespace App\Models\Recipient;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property integer $id
 * @property string $telegram_id
 * @property string|null $name
 * @property boolean $can_create_recipients Can create recipients and add users to them in the bot
 * @property boolean $can_manage_ledger_accounts Can link wallets to ledger accounts in the bot
 *
 * @property Recipient[] $recipients
 * @property string $selectLabel
 */
class TgUser extends BaseModel
{
    use AsSource, SoftDeletes, Filterable;

    protected $table = 'tg_users';

    protected $fillable = [
        'telegram_id',
        'name',
        'can_create_recipients',
        'can_manage_ledger_accounts',
    ];

    protected $casts = [
        'can_create_recipients' => 'boolean',
        'can_manage_ledger_accounts' => 'boolean',
    ];

    protected $allowedFilters = [];
    protected $allowedSorts = ['id', 'name', 'telegram_id', 'created_at'];

    protected static function booted(): void
    {
        // On (soft) delete, detach from all recipients
        static::deleting(function (TgUser $user) {
            $user->recipients()->detach();
        });
    }


    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'recipient_tg_user');
    }

    /**
     * Display name for Select: "John (100000001)"
     */
    public function getSelectLabelAttribute(): string
    {
        return $this->name
            ? "{$this->name} ({$this->telegram_id})"
            : $this->telegram_id;
    }

    /**
     * Find a user by telegram_id
     */
    public static function findByTelegramId(int|string $telegramId): ?self
    {
        return self::where('telegram_id', (string)$telegramId)->first();
    }

    /**
     * Get the list of recipient_id values for the given telegram_id.
     */
    public static function getRecipientIds(int|string $telegramId): array
    {
        $user = self::findByTelegramId($telegramId);

        if (!$user) {
            return [];
        }

        return $user->recipients()->pluck('recipients.id')->toArray();
    }
}
