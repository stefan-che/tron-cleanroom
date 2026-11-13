<?php

namespace App\Models\Aml;

use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property int $aml_address_id
 * @property int $status
 * @property string $error
 * @property string $current_transaction_hash
 * @property string $last_check_transaction_hash
 * @property string $current_trx_transaction_hash
 * @property string $last_check_trx_transaction_hash
 * @property float $aml_risk_score
 * @property string $aml_file_report_link
 * @property ?string $local_report_path
 * @property array $response
 * @property int $current_tx_count
 * @property float $aml_balance
 * @property ?int $aml_service_id
 * @property ?string $uid
 * @property ?array $signals
 * @property ?array $counterparty
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $check_finish_at
 * @property string $status_name
 * @property AmlService $amlService
 */
class AmlAddressCheck extends BaseModel
{
    use HasFactory, AsSource, SoftDeletes;

    const int STATUS_PENDING = 0;
    const int STATUS_COMPLETED = 1;
    const int STATUS_SKIPPED = 2;
    const int STATUS_FAILED = 3;

    protected $table = 'aml_addresses_checks';

    protected $guarded = [];

    protected $casts = [
        'check_finish_at' => 'datetime',
        'response' => 'array',
        'signals' => 'array',
        'counterparty' => 'array',
    ];

    public static function statusNames(): array
    {
        return [
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_SKIPPED   => 'Skipped',
            self::STATUS_FAILED    => 'Failed',
        ];
    }

    /**
     * status_name
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return self::statusNames()[$this->status] ?? '-';
    }

    /**
     * @return BelongsTo
     */
    public function amlAddress(): BelongsTo
    {
        return $this->belongsTo(AmlAddress::class);
    }

    /**
     * @return BelongsTo
     */
    public function amlService(): BelongsTo
    {
        return $this->belongsTo(AmlService::class, 'aml_service_id');
    }


    /**
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return bool
     */
    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
