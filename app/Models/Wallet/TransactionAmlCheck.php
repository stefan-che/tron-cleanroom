<?php

namespace App\Models\Wallet;

use App\Models\Aml\AmlService;
use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property int $transaction_id
 * @property int $service_id
 * @property ?float $risk_score
 * @property ?string $file_report_link
 * @property ?string $local_report_path
 * @property ?string $uid
 * @property ?array $response
 * @property ?array $signals
 * @property ?array $counterparty
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $check_finish_at
 *
 * @property Transaction $transaction
 * @property AmlService $service
 * @property string $status_name
 * @property float $risk_score_normalize
 * @property string $report_file_url
 */
class TransactionAmlCheck extends BaseModel
{
    use HasFactory, AsSource, SoftDeletes;

    const int STATUS_PENDING = 0;
    const int STATUS_COMPLETED = 1;
    const int STATUS_SKIPPED = 2;
    const int STATUS_FAILED = 3;

    protected $table = 'transactions_aml_checks';

    protected $guarded = [];

    protected $casts = [
        'response' => 'array',
        'signals' => 'array',
        'counterparty' => 'array',
        'check_finish_at' => 'datetime',
        'risk_score' => 'float',
    ];

    /**
     * @return BelongsTo
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * risk_score_normalize
     * @return ?float
     */
    public function getRiskScoreNormalizeAttribute(): ?float
    {
        $result = $this->risk_score;
        if (!is_null($result) && $result < 1.001) {
            $result *= 100;
        }

        return round($result, 2);
    }

    /**
     * @return BelongsTo
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(AmlService::class, 'service_id');
    }

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

    /**
     * @return bool
     */
    public function hasRisk(): bool
    {
        return !is_null($this->risk_score) && $this->risk_score > 0;
    }

    /**
     * report_file_url
     * Direct link to the PDF report: local file or external URL.
     * @return string
     */
    public function getReportFileUrlAttribute(): string
    {
        if ($this->local_report_path) {
            return asset('storage/' . $this->local_report_path);
        }

        return $this->file_report_link ?? '';
    }
}
