<?php

namespace App\Models\Wallet;

use App\Models\Ai\AiService;
use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property int $wallet_id
 * @property ?int $ai_service_id
 * @property ?string $wallet_color
 * @property ?string $risk
 * @property ?int $confidence
 * @property ?string $summary
 * @property ?string $prompt
 * @property ?array $request_payload
 * @property ?array $raw_response
 * @property ?array $coinkyt_result
 * @property ?string $coinkyt_wallet_color
 * @property ?string $coinkyt_risk
 * @property int $status
 * @property ?string $error
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Wallet $wallet
 * @property ?AiService $service
 */
class WalletAiAmlCheck extends BaseModel
{
    use HasFactory, AsSource;

    const int STATUS_PENDING = 0;
    const int STATUS_COMPLETED = 1;
    const int STATUS_FAILED = 3;

    const string COLOR_WHITE = 'WHITE';
    const string COLOR_YELLOW = 'YELLOW';
    const string COLOR_GREY = 'GREY';
    const string COLOR_BLACK = 'BLACK';

    protected $table = 'wallet_ai_aml_checks';

    protected $guarded = [];

    protected $casts = [
        'request_payload' => 'array',
        'raw_response' => 'array',
        'coinkyt_result' => 'array',
        'confidence' => 'integer',
        'status' => 'integer',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AiService::class, 'ai_service_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Completed checks only.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
