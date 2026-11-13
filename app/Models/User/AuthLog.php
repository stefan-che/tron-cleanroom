<?php

namespace App\Models\User;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthLog extends BaseModel
{
    public const int TYPE_LOGIN = 1;
    public const int STATUS_SUCCESS = 1;
    public const int STATUS_FAILED = 2;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'browser',
        'location',
        'status',
        'type',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_SUCCESS => __('auth_logs.status_success'),
            self::STATUS_FAILED => __('auth_logs.status_failed'),
        ];
    }

    /**
     * status_name
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return self::statuses()[$this->status] ?? '-';
    }


}
