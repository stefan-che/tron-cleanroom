<?php

namespace App\Models\Wallet;

use App\Casts\StripTagsFromJsonCast;
use App\Models\BaseModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property int $action
 * @property int $wallet_id
 * @property int $user_id
 * @property string $action_name
 * @property array $payload
 *
 * @property Carbon $created_at
 * @property User $user
 */
class WalletLog extends BaseModel
{
    use HasFactory, AsSource;

    protected $table = 'wallets_logs';

    public const int ACTIVATE_SUCCESS = 1;
    public const int ACTIVATE_FAILED = 2;

    public const int ADD_ENERGY_SUCCESS = 3;
    public const int ADD_ENERGY_FAILED = 4;

    public const int ADD_TRX_SUCCESS = 5;
    public const int ADD_TRX_FAILED = 6;

    public const int AML_CHECK = 7;
    public const int SEND_USDT_SUCCESS = 8;
    public const int SEND_USDT_FAILED = 9;
    public const int SEND_TRX_SUCCESS = 10;
    public const int SEND_TRX_FAILED = 11;
    public const int SET_WALLET_TO = 12;

    protected $guarded = [];

    protected $casts = [
        'payload' => StripTagsFromJsonCast::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActionNameAttribute(): string
    {
        return match ($this->action) {
            self::ACTIVATE_SUCCESS => 'Wallet activated successfully',
            self::ACTIVATE_FAILED => 'Wallet activation failed',
            self::ADD_ENERGY_SUCCESS => 'Energy added successfully',
            self::ADD_ENERGY_FAILED => 'Adding energy failed',
            self::ADD_TRX_SUCCESS => 'TRX added successfully',
            self::ADD_TRX_FAILED => 'Adding TRX failed',
            self::AML_CHECK => 'AML check',
            self::SEND_USDT_SUCCESS => 'USDT sent successfully ',
            self::SEND_USDT_FAILED => 'Sending USDT failed',
            self::SEND_TRX_SUCCESS => 'TRX sent to the TRX wallet successfully ',
            self::SEND_TRX_FAILED => 'Sending TRX failed',
            self::SET_WALLET_TO => 'Setting the address the funds will be sent to',
            default => '-',
        };
    }
}
