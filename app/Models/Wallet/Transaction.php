<?php

namespace App\Models\Wallet;

use App\Enums\TransactionStatuses;
use App\Enums\WalletStatuses;
use App\Helpers\Development;
use App\Jobs\Aml\AmlBot\AmlTransactionCheck;
use App\Models\Aml\AmlAddressCheck;
use App\Models\BaseModel;
use App\Models\Recipient\Recipient;
use App\Models\Setting;
use App\Repositories\WalletRepository;
use App\Services\Aml\Services\AmlBotService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property integer $id
 * @property float $aml_risk_score
 * @property float $amount
 * @property int $status
 * @property int $wallet_id
 * @property string $aml_file_report_link
 * @property string $aml_uid
 * @property string $tx_id
 * @property string $from_address
 * @property string $send_tx_hash
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $send_at
 *
 * @property array $signals_groups
 * @property string $status_name
 * @property string $status_name_en
 * @property string $tronscan_tx_link
 * @property string $tronscan_send_tx_link
 * @property string $tronscan_from_address_link
 * @property Wallet $wallet
 * @property Recipient $recipient
 * @property AmlLog[] $amlLogs
 * @property AmlLog $lastAmlLog
 * @property AmlLog $lastAmlLogByTypeAmlBot
 * @property \App\Models\Wallet\TransactionAmlCheck[] $checks
 *
 */
class Transaction extends BaseModel
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wallet_id',
        'recipient_id',
        'tx_id',
        'tx_data',
        'from_address',
        'amount',
        'status',
        'aml_risk_score',
        'aml_file_report_link',
        'aml_uid',
        'send_tx_hash',
        'send_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'tx_data' => 'json',
        'send_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(static function (self $transaction) {
        });
    }

    public function recipient()
    {
        return $this->belongsTo(Recipient::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->status === TransactionStatuses::NEW->value;
    }

    /**
     * @return bool
     */
    public function isVerificationComplete(): bool
    {
        return $this->status === TransactionStatuses::FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY->value;
    }

    /**
     * @return bool
     */
    public function isVerificationFailed(): bool
    {
        return $this->status === TransactionStatuses::FUNDS_VERIFICATION_FAILED->value;
    }

    /**
     * @return bool
     */
    public function isAmlProcess(): bool
    {
        return $this->status === TransactionStatuses::PROCESS->value;
    }

    /**
     * status_name
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '-';
    }

    /**
     * status_name_en
     * @return string
     */
    public function getStatusNameEnAttribute(): string
    {
        return self::getStatusesEn()[$this->status] ?? '-';
    }

    /**
     * signals_groups
     * @return array
     */
    public function getSignalsGroupsAttribute(): array
    {
        $groupedSignals = ['low' => [], 'middle' => [], 'high' => []];

        if (is_null($this->aml_risk_score)) {
            return $groupedSignals;
        }

        $logs = $this->amlLogs;

        $formatSignal = function (string $signal) {
            $formatted = str_replace(['_'], ' ', $signal);
            return ucwords(strtolower($formatted));
        };

        foreach ($logs as $log) {
            $signals = $log->response['data']['signals'] ?? [];

            if (!$signals) {
                continue;
            }

            foreach ($signals as $signal => $value) {
                if (!$value) {
                    continue;
                }

                foreach (Wallet::getAmlSignalGroups() as $group => $signals) {
                    if (in_array($signal, $signals, true)) {
                        $groupedSignals[$group][$formatSignal($signal)] = $value * 10;
                        break;
                    }
                }
            }
        }

        return $groupedSignals;
    }


    /**
     * tronscan_tx_link
     * @return string
     */
    public function getTronscanTxLinkAttribute(): string
    {
        return 'https://tronscan.org/#/transaction/' . $this->tx_id;
    }

    /**
     * tronscan_send_tx_link
     * @return string
     */
    public function getTronscanSendTxLinkAttribute(): string
    {
        return 'https://tronscan.org/#/transaction/' . $this->send_tx_hash;
    }

    /**
     * tronscan_from_address_link
     * @return string
     */
    public function getTronscanFromAddressLinkAttribute(): string
    {
        return 'https://tronscan.org/#/address/' . $this->from_address;
    }

    public static function getStatuses(): array
    {
        return [
            TransactionStatuses::NEW->value => 'new',
            TransactionStatuses::PROCESS->value => 'process',
            TransactionStatuses::FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY->value => 'AML testing was completed successfully',
            TransactionStatuses::FUNDS_VERIFICATION_FAILED->value => 'AML testing was not successful',
            TransactionStatuses::AML_ERROR->value => 'Aml error',
        ];
    }

    /**
     * todo: remove this method once transaction statuses are confirmed to be migrated to the new ones
     * @return string[]
     */
    public static function getStatusesEn(): array
    {
        return [
            TransactionStatuses::NEW->value => 'new',
            TransactionStatuses::PROCESS->value => 'process',
            TransactionStatuses::FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY->value => 'aml_completed_successfully',
            TransactionStatuses::FUNDS_VERIFICATION_FAILED->value => 'aml_not_successful',
            TransactionStatuses::AML_ERROR->value => 'aml_error',
        ];
    }

    public function statusHandler(): void
    {
        return; // done manually

        /**
         * @var WalletRepository $walletRepository
         */
        $walletRepository = app(WalletRepository::class);

        if ($this->wallet->isAmlCheck() && is_null($this->aml_risk_score)) {

            $walletRepository->update($this->wallet, [
                'status' => WalletStatuses::CHECK_TRANSACTION_START->value,
            ]);

            AmlTransactionCheck::dispatch($this->id)
                ->delay(now()->addMinutes(Development::isLocal() ? 0 : 2));
            return;
        }

        if ($this->isAmlProcess()) {
        }

        if ($this->isVerificationComplete() || $this->isVerificationFailed()) {
            $walletRepository->update($this->wallet, [
                'status' => WalletStatuses::CHECK_TRANSACTION_FINISH->value,
            ]);
        }
    }

    public function amlLogs(): HasMany
    {
        return $this->hasMany(AmlLog::class)->latest();
    }

    public function lastAmlLog(): HasOne
    {
        return $this->hasOne(AmlLog::class)->latest();
    }

    public function lastAmlLogByTypeAmlBot(): HasOne
    {
        return $this->hasOne(AmlLog::class)->amlBot()->latest();
    }

    public function checks(): HasMany
    {
        return $this->hasMany(TransactionAmlCheck::class);
    }

    /**
     * Returns the check record for a specific AML service by its code.
     * Used when the uid/hash of a specific provider is needed.
     */
    public function checksForService(string $serviceCode): ?TransactionAmlCheck
    {
        $serviceId = \App\Models\Aml\AmlService::where('code', $serviceCode)->value('id');
        if (!$serviceId) {
            return null;
        }

        return $this->checks()->where('service_id', $serviceId)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|TransactionAmlCheck
     */
    public function addCheckToHistory(
        int $serviceId,
        float $riskScore,
        string $fileReportLink,
        string|int|null $uid,
        array $response = [],
        array $signals = [],
        array $counterparty = [],
        array $signalGroups = [],
        bool $transformSignalKeys = true,
    ): \Illuminate\Database\Eloquent\Model
    {
        return $this->checks()->updateOrCreate(
            ['service_id' => $serviceId],
            [
                'risk_score' => $riskScore,
                'file_report_link' => $fileReportLink,
                'uid' => $uid,
                'response' => $response,
                'signals' => Wallet::formatSignals($signals, $signalGroups ?: null, $transformSignalKeys),
                'counterparty' => $counterparty,
                'status' => TransactionAmlCheck::STATUS_COMPLETED,
                'check_finish_at' => now(),
            ]
        );
    }

    /**
     * Get the latest AML checks of the transaction grouped by service
     * @return \Illuminate\Support\Collection
     */
    public function getLatestAmlChecks(): \Illuminate\Support\Collection
    {
        $this->loadMissing('checks.service');

        return $this->checks
            ->groupBy(fn($check) => $check->service?->code)
            ->map(fn($group) => $group->sortByDesc('check_finish_at')->first());
    }

    /**
     * @return array
     */
    public function getCounterPartyData(): array
    {
        $amlLog = $this->lastAmlLogByTypeAmlBot;

        if (!$amlLog || !$amlLog->response) {
            return [];
        }

        $response = $amlLog->response;

        /**
         * todo: after 1.04 take from own counterparty in checks
         */
        $counterparty = (new AmlBotService())->extractCounterparty($response);
        return $counterparty;

        return $response['data']['counterparty'] ?? $response['counterparty'] ?? [];
    }

    /**
     * Whether allowed
     * AML is checked only for "incoming" (buy) operations
     * @return bool
     */
    public function isAmlCheck(): bool
    {
        if ($this->wallet && !$this->wallet->isOperationBuy()) {
            return false;
        }

        return $this->amount >= Setting::getAmlSumCheck() && is_null($this->aml_risk_score);
    }

}
