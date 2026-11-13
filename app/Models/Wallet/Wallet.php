<?php

namespace App\Models\Wallet;

use App\Enums\WalletOperationsTypes;
use App\Enums\WalletStatuses;
use App\Enums\TransactionStatuses;
use App\Helpers\MathHelper;
use App\Models\BaseModel;
use App\Models\Recipient\Recipient;
use App\Models\Setting;
use App\Models\Aml\AmlService;
use App\Models\Aml\CoinKytMonitoredAddress;
use App\Models\Aml\CoinKytMonitoringEvent;
use App\Services\Aml\Services\AmlBotService;
use App\Services\Wallet\KeyManager;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;
use \App\Models\Recipient\Wallet as RecipientWallet;

/**
 * @property integer $id
 * @property integer $connect_type
 * @property int $has_error
 * @property int $error
 * @property int $status
 * @property int $type
 * @property int $operation_type
 * @property int $recipient_wallet_id
 * @property float $aml_risk_score
 * @property string $aml_file_report_link
 * @property float $amount - USDT amount
 * @property float $amount_energy
 * @property float $amount_trx
 * @property string $address
 * @property string $private_key_hash
 * @property string $private_key_salt
 * @property string $private_key_secret
 * @property string $send_tx_hash
 * @property string $address_to
 * @property string $address_hex
 * @property string $private_key
 * @property string $public_key
 * @property string $external_id
 * @property int $recipient_id
 * @property int $blockchain_type
 * @property string $comment
 * @property string $tx_id
 * @property float $last_transfer_amount
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $expire_energy_at
 * @property int $is_test
 * @property float $energy_balance
 * @property float $energy_cost_trx
 * @property string $saved_key
 *
 * @property float $aml_risk_score_normalize
 * @property string $link_view_admin
 * @property string $external_id_format
 * @property string $regexp
 * @property string $tronscan_address_link
 * @property string $tronscan_address_to_link
 * @property string $aml_code
 * @property string $status_name
 * @property string $status_name_en
 * @property string $type_name
 * @property string $operation_type_name
 * @property Transaction[] $transactions
 * @property Recipient $recipient
 * @property WalletLog[] $logs
 * @property WalletWebhook[] $webhooks
 * @property array $info
 * @property string $well_wallet_network
 * @property RecipientWallet $recipientWallet
 * @property WalletAiAmlCheck[] $aiAmlChecks
 */
class Wallet extends BaseModel
{

    use Filterable, AsSource;

    const int CRYPTO_CONNECT_3 = 3;
    const int CRYPTO_CONNECT_4 = 4;
    const int CRYPTO_DEFAULT_CONNECT = self::CRYPTO_CONNECT_3;
    const int LISTEN_DAYS = 3;
    const int ENERGY_WALLET_FOR_NEW = 131500;
    const int ENERGY_WALLET_FOR_ACTIVATE = 65150;
    const string PREFIX_EXTERNAL_ID = 'payment_';

    /**
     * Extra TRX in rare cases when below the free TRX norm
     */
    const int ADDITIONAL_TRX = 8;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'connect_type',
        'error',
        'blockchain_type',
        'has_error',
        'status',
        'type',
        'operation_type',
        'recipient_wallet_id',
        'address',
        'address_to',
        'send_tx_hash',
        'address_hex',
        'private_key',
        'private_key_hash',
        'private_key_salt',
        'private_key_secret',
        'public_key',
        'external_id',
        'recipient_id',
        'comment',
        'amount',
        'amount_energy',
        'amount_trx',
        'expire_energy_at',
        'aml_risk_score',
        'aml_file_report_link',
        'last_transfer_amount',
        'is_test',
        'energy_balance',
        'energy_cost_trx',
        'saved_key',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(static function (self $wallet) {
            if ($wallet->isTestWallet() && $wallet->wasChanged('status')) {
                $wallet->runTestOperation();
            }
        });
    }


    public function recipient()
    {
        return $this->belongsTo(Recipient::class);
    }

    public function recipientWallet()
    {
        return $this->belongsTo(RecipientWallet::class);
    }


    public function logs(): HasMany
    {
        return $this->hasMany(WalletLog::class)->oldest();
    }

    /**
     * webhooks
     * @return HasMany
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(WalletWebhook::class)->oldest();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->oldest();
    }

    /**
     * AI AML check results for the wallet
     * @return HasMany
     */
    public function aiAmlChecks(): HasMany
    {
        return $this->hasMany(WalletAiAmlCheck::class)->latest();
    }

    /**
     * CoinKYT monitoring record for the wallet address
     * @return HasOne
     */
    public function coinKytMonitoring(): HasOne
    {
        return $this->hasOne(CoinKytMonitoredAddress::class);
    }

    /**
     * CoinKYT monitoring events for the wallet
     * @return HasMany
     */
    public function coinKytMonitoringEvents(): HasMany
    {
        return $this->hasMany(CoinKytMonitoringEvent::class)->latest('event_at');
    }

    public function scopeByStatus(Builder $query, int $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNew(Builder $query)
    {
        return $query->byStatus(WalletStatuses::NEW->value);
    }

    public function scopeCompleted(Builder $query)
    {
        return $query->byStatus(WalletStatuses::COMPLETED->value);
    }

    public function scopeTimeOut(Builder $query)
    {
        return $query->byStatus(WalletStatuses::TIME_OUT->value);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeSyncNewTransaction(Builder $query)
    {
        return $query->whereIn('status', [
            WalletStatuses::NEW->value,
            WalletStatuses::ADD_TRANSACTION->value,
            WalletStatuses::CHECK_TRANSACTION_START->value,
            WalletStatuses::CHECK_TRANSACTION_FINISH->value,
        ]);
    }

    public function scopeSyncFinishTransaction(Builder $query)
    {
        return $query->whereIn('status', [
            WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value,
        ]);
    }

    /**
     * Go through completed wallets and check for new transactions
     * @param Builder $query
     * @return Builder
     */
    public function scopeSyncOldTransaction(Builder $query)
    {
        return $query->whereIn('status', [
            WalletStatuses::COMPLETED->value,
        ]);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeSyncUpdatedDays(Builder $query, int $days = self::LISTEN_DAYS)
    {
        return $query->whereDate('updated_at', '>=', now()->subDays($days));
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeUpdatedOldDays(Builder $query, int $days = 5)
    {
        return $query->whereDate('updated_at', '<', now()->subDays($days));
    }

    public function scopeSyncForCompleted(Builder $query, int $hours = 24)
    {
        return $query->whereIn('status', [
            WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value, // Update amounts after the sweep
        ])
            ->where('updated_at', '<', now()->subHours($hours));
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeNotTest(Builder $query)
    {
        return $query->where('is_test', false);
    }

    /**
     * regexp
     * @return string
     */
    public function getRegexpAttribute(): string
    {
        return RecipientWallet::blockChainTypesRegexp()[$this->blockchain_type] ?? '';
    }

    /**
     * aml_code
     * @return string
     */
    public function getAmlCodeAttribute(): string
    {
        return self::amlCodes()[$this->blockchain_type] ?? '';
    }

    /**
     * external_id_format
     * @return string
     */
    public function getExternalIdFormatAttribute(): string
    {
        return sprintf('/%s%s', self::PREFIX_EXTERNAL_ID, $this->external_id);
    }

    public static function amlCodes(): array
    {
        return [
            RecipientWallet::BLOCKCHAIN_TYPE_TRC_20 => 'TRX',
            RecipientWallet::BLOCKCHAIN_TYPE_BTC => 'BTC',
            RecipientWallet::BLOCKCHAIN_TYPE_ETH => 'ETH',
        ];
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
     * type_name
     * @return string
     */
    public function getTypeNameAttribute(): string
    {
        return RecipientWallet::typesEng()[$this->type] ?? '----';
    }

    /**
     * operation_type_name
     * @return string
     */
    public function getOperationTypeNameAttribute(): string
    {
        return WalletOperationsTypes::namesEn()[$this->operation_type] ?? '----';
    }

    /**
     * Incoming payment (the payer sends crypto to the transit wallet), the main scenario
     * @return bool
     */
    public function isOperationBuy(): bool
    {
        return (int)$this->operation_type === WalletOperationsTypes::BUY->value;
    }

    /**
     * Payout (crypto goes to the specified recipient address); no AML check
     * @return bool
     */
    public function isOperationSell(): bool
    {
        return (int)$this->operation_type === WalletOperationsTypes::SELL->value;
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeOperationBuy(Builder $query)
    {
        return $query->where('operation_type', WalletOperationsTypes::BUY->value);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeOperationSell(Builder $query)
    {
        return $query->where('operation_type', WalletOperationsTypes::SELL->value);
    }

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->status === WalletStatuses::NEW->value;
    }

    /**
     * @return bool
     */
    public function isCheckTransactionFinish(): bool
    {
        return $this->status === WalletStatuses::CHECK_TRANSACTION_FINISH->value;
    }

    /**
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === WalletStatuses::COMPLETED->value;
    }

    /**
     * @return bool
     */
    public function isTimeOut(): bool
    {
        return $this->status === WalletStatuses::TIME_OUT->value;
    }

    /**
     * @return bool
     */
    public function isAddTransaction(): bool
    {
        return $this->status === WalletStatuses::ADD_TRANSACTION->value;
    }

    /**
     * @return bool
     */
    public function isCheckTransactionStart(): bool
    {
        return $this->status === WalletStatuses::CHECK_TRANSACTION_START->value;
    }

    /**
     * @return bool
     */
    public function isTransferMoneyProcessFinish(): bool
    {
        return $this->status === WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value;
    }

    /**
     * @return bool
     */
    public function isTransferMoneyProcessStart(): bool
    {
        return $this->status === WalletStatuses::TRANSFER_MONEY_PROCESS_START->value;
    }

    /**
     * The transfer of funds is already fully completed.
     * After a partial send the status returns to CHECK_TRANSACTION_FINISH,
     * so the remainder can be sent again.
     *
     * @return bool
     */
    public function isSendCompleted(): bool
    {
        return (bool)$this->send_tx_hash && (
            $this->isTransferMoneyProcessFinish() ||
            $this->status === WalletStatuses::COMPLETED->value
        );
    }


    public static function getStatuses(): array
    {
        return [
            WalletStatuses::NEW->value => 'New',
            WalletStatuses::ADD_TRANSACTION->value => 'Transaction added',
            WalletStatuses::CHECK_TRANSACTION_START->value => 'Transaction check started',
            WalletStatuses::CHECK_TRANSACTION_FINISH->value => 'Transaction check finished',
            WalletStatuses::TRANSFER_MONEY_PROCESS_START->value => 'Funds transfer initiated',
            WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value => 'Funds transfer finished',
            WalletStatuses::COMPLETED->value => 'Completed',
            WalletStatuses::TIME_OUT->value => 'Timed out',
        ];
    }

    public static function getStatusesEn(): array
    {
        return [
            WalletStatuses::NEW->value => 'new',
            WalletStatuses::ADD_TRANSACTION->value => 'add_transaction',
            WalletStatuses::CHECK_TRANSACTION_START->value => 'check_transaction_start',
            WalletStatuses::CHECK_TRANSACTION_FINISH->value => 'check_transaction_finish',
            WalletStatuses::TRANSFER_MONEY_PROCESS_START->value => 'transfer_money_process_start',
            WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value => 'transfer_money_process_finish',
            WalletStatuses::COMPLETED->value => 'completed',
            WalletStatuses::TIME_OUT->value => 'time_out',
        ];
    }

    /**
     * @return bool
     */
    public function isTrc20(): bool
    {
        return $this->blockchain_type === RecipientWallet::BLOCKCHAIN_TYPE_TRC_20;
    }

    public function getDecPrivateKey(): string
    {
        if ($this->saved_key) {
            try {
            } catch (\Throwable $e) {
            }
        }

        return $this->decPrivateKeyFromManager();
    }

    /**
     * Decrypts the private key via KeyManager (private_key + secret + salt).
     */
    public function decPrivateKeyFromManager(): string
    {
        $result = (new KeyManager())->decrypt(
            $this->private_key,
            $this->private_key_secret,
            $this->private_key_salt,
            $this->connect_type,
        );

        return $result['result'] ?? '';
    }

    /**
     * Decrypts the private key from saved_key (Laravel Crypt).
     * Empty string if the field is empty or decryption failed.
     */
    public function decPrivateKeyFromSaved(): string
    {
        if (!$this->saved_key) {
            return '';
        }

        try {
            return Crypt::decryptString($this->saved_key);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * AML is checked only for "incoming" (buy) operations
     * @return bool
     */
    public function isAmlCheck(): bool
    {
        if (!$this->isOperationBuy()) {
            return false;
        }

        return $this->amount >= Setting::getAmlSumCheck() && is_null($this->aml_risk_score);
    }

    /**
     * tronscan_address_link
     * @return string
     */
    public function getTronscanAddressLinkAttribute(): string
    {
        return $this->address ? ('https://tronscan.org/#/address/' . $this->address) : '';
    }

    /**
     * tronscan_address_to_link
     * @return string
     */
    public function getTronscanAddressToLinkAttribute(): string
    {
        return $this->address_to ? ('https://tronscan.org/#/address/' . $this->address_to) : '';
    }

    /**
     * info
     * @return array
     */
    public function getInfoAttribute(): array
    {
        //blockchain_type
        return [
            'name' => 'Tether Token',
            'symbol' => 'USDT',
            'decimals' => 6,
        ];
    }

    /**
     * Formats raw signals from any AML service into a unified format:
     * ['low' => ['Exchange' => 3.0], 'middle' => [...], 'high' => [...]]
     *
     * @param array $rawSignals {'signal_code' => 0-1 value}
     * @param array|null $signalGroups Signal grouping of a specific service.
     *                                  If null, AmlBot groups are used (backward compatibility).
     * @param bool $transformKeys true = convert snake_case keys into readable labels (AmlBot/GetBlock).
     *                             false = use the key as the label unchanged (WellWallet etc.)
     */
    public static function formatSignals(array $rawSignals, ?array $signalGroups = null, bool $transformKeys = true): array
    {
        $result = ['low' => [], 'middle' => [], 'high' => []];
        $groups = $signalGroups ?? self::getAmlSignalGroups();

        foreach ($rawSignals as $signal => $value) {
            if (!$value) {
                continue;
            }

            $label = $transformKeys
                ? ucwords(strtolower(str_replace('_', ' ', $signal)))
                : $signal;

            foreach ($groups as $group => $groupSignals) {
                if (in_array($signal, $groupSignals, true)) {
                    $result[$group][$label] = round((float)$value * 10, 2);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * link_view_admin
     * @return string
     */
    public function getLinkViewAdminAttribute(): string
    {
        return route('platform.operation.info', $this);
    }


    public static function getAmlSignalGroups(): array
    {
        return (new AmlBotService())->getSignalGroups();
    }

    /**
     *
     * @return bool
     */
    public function isOldWallet(): bool
    {
        return $this->updated_at <= now()->subDays(2);
    }

    /**
     * aml_risk_score_normalize
     * @return ?float
     */
    public function getAmlRiskScoreNormalizeAttribute(): ?float
    {
        if (!is_null($this->aml_risk_score) && $this->aml_risk_score < 1) {
            return $this->aml_risk_score * 100;
        }

        return $this->aml_risk_score;
    }

    /**
     * Test wallet
     * @return bool
     */
    public function isTestWallet(): bool
    {
        return $this->is_test;
    }

    /**
     * well_wallet_network
     * @return string
     */
    public function getWellWalletNetworkAttribute(): string
    {
        //trx,eth,btc,sol,bnb
        return strtolower($this->aml_code);
    }

    /**
     * Wallet mismatch: the risk exceeds the threshold for the selected wallet
     * @return true
     */
    public function isShowRiskLabel(): bool
    {
        if (!$this->address_to) {
            return false;
        }

        if ($this->isSendTypeWhite() && $this->aml_risk_score_normalize > 10) {
            return true;
        }

        if ($this->isSendTypeGray()) {
            if ($this->aml_risk_score_normalize <= 10 || $this->aml_risk_score_normalize > 20) {
                return true;
            }
        }

        return false;
    }

    public function isWellWalletCheck(): true
    {
        //trx,eth,btc,sol,bnb
        return in_array($this->blockchain_type, [
            RecipientWallet::BLOCKCHAIN_TYPE_TRC_20,
            RecipientWallet::BLOCKCHAIN_TYPE_BTC,
            RecipientWallet::BLOCKCHAIN_TYPE_ETH,
        ], true);
    }


    public function isSendTypeWhite(): bool
    {
        return $this->type === RecipientWallet::TYPE_WHITE;
    }

    public function isSendTypeGray(): bool
    {
        return $this->type === RecipientWallet::TYPE_GRAY;
    }

    public function isSendTypeBlack(): bool
    {
        return $this->type === RecipientWallet::TYPE_BLACK;
    }

    public function isSendTypeYellow(): bool
    {
        return $this->type === RecipientWallet::TYPE_YELLOW;
    }

    /**
     * Refund to the payer's wallet
     * @return bool
     */
    public function isReturnToSameAddress(): bool
    {
        return $this->transactions()
            ->whereNull('send_tx_hash')
            ->where('from_address', $this->address_to)
            ->exists();
    }

    public function runTestOperation(): void
    {
        if ($this->isAddTransaction()) {
            if ($this->transactions()->exists()) {
            }

            $this->createFakeTransaction();
        }

        if ($this->isCheckTransactionFinish()) {
            $this->setFakeAmlResultTransaction();
        }
    }

    /**
     * Create a fake transaction for the test wallet
     * @return Transaction
     * @throws \Exception
     */
    public function createFakeTransaction(): Transaction
    {
        $amount = random_int(101, 100000);

        $this->incrementQuietly('amount', $amount);

        return $this->transactions()->create([
            'wallet_id' => $this->id,
            'recipient_id' => $this->recipient_id,
            'tx_id' => 'fake_' . bin2hex(random_bytes(8)),
            'tx_data' => ['fake' => true],
            'from_address' => $this->address_to ?: 'fake_from_address_555',
            'amount' => $amount,
            'status' => TransactionStatuses::NEW->value,
            'aml_risk_score' => null,
            'aml_file_report_link' => null,
            'aml_uid' => null,
            'send_tx_hash' => null,
        ]);
    }

    public function setFakeAmlResultTransaction(): void
    {
        $riskScore = MathHelper::randomFloat(0.1, 0.9);
        $report = 'https://example.com/aml-report/transaction/8034';
        $report = 'fake_' . bin2hex(random_bytes(8));

        $this->updateQuietly([
            'aml_file_report_link' => $report,
            'aml_risk_score' => $riskScore,
        ]);

        $this->transactions()->update([
            'aml_file_report_link' => $report,
            'aml_risk_score' => $riskScore,
            'status' => TransactionStatuses::FUNDS_VERIFICATION_COMPLETED_SUCCESSFULLY->value,
        ]);

        $transaction = $this->transactions()->reorder()->latest()->first();
        if (!$transaction) {
            return;
        }

        $fakeChecks = [
            [
                'service' => AmlService::amlBot()->first(),
                'signals' => [
                    'low' => ['Exchange' => round($riskScore * 10, 2)],
                    'middle' => [],
                    'high' => [],
                ],
                'counterparty' => ['Binance' => 70, 'OKX' => 30],
                'report' => $report,
            ],
            [
                'service' => AmlService::wellWallet()->first(),
                'signals' => [
                    'low' => ['Exchange' => 10],
                    'middle' => [],
                    'high' => [],
                ],
                'counterparty' => ['Bybit' => 100],
                'report' => 'https://example.com/aml-report/transaction/8035',
            ],
            [
                'service' => AmlService::coinKyt()->first(),
                'signals' => [
                    'low' => ['Exchange' => 10],
                    'middle' => [],
                    'high' => [],
                ],
                'counterparty' => ['Bybit' => 100],
                'report' => 'https://example.com/aml-report/transaction/8035',
            ],
        ];

        foreach ($fakeChecks as $data) {
            $amlService = $data['service'];
            if (!$amlService) {
                continue;
            }

            $transaction->checks()->updateOrCreate(
                ['service_id' => $amlService->id],
                [
                    'status' => TransactionAmlCheck::STATUS_COMPLETED,
                    'risk_score' => $riskScore,
                    'uid' => null,
                    'response' => [],
                    'signals' => $data['signals'],
                    'counterparty' => $data['counterparty'],
                    'file_report_link' => $data['report'],
                    'check_finish_at' => now(),
                ]
            );
        }
    }

    public function isDangerTableRow(): bool
    {
        return $this->has_error || $this->isTestWallet();
    }

    public function isShowOldAddressToLabel(): bool
    {
        $days = $this->created_at <= now()->subDays(1);
        $recipientWallet = $this->recipientWallet?->address;
        return $days || ($recipientWallet && $this->address_to !== $recipientWallet);
    }
}
