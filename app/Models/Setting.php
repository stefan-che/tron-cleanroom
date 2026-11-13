<?php

namespace App\Models;

use App\Helpers\ArrayHelper;
use App\Models\Wallet\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property string $description
 */
class Setting extends BaseModel
{
    use HasFactory, AsSource, Filterable;

    private static array $data = [];

    const string COUNT_ENERGY_SEND_TR = 'count_energy_by_send_tr';

    public const string TRX_AMOUNT = 'trx_amount';

    public const string AML_SUM_CHECK = 'aml_sum_check';

    public const string ALERT_ENERGY_MIN_SUM_CHECK = 'alert_energy_min_sum_check';

    const string COUNT_TRX_FOR_ACTIVATE_WALLET = 'count_trx_for_activate_wallet';
    const string TELEGRAM_USERS_ID = 'telegram_users_ids';
    const string OPERATIONS_BOT_TELEGRAM_USERS_ID = 'operations_bot_telegram_users_ids';
    const string MINIMUM_TR_AMOUNT = 'min_tr_amount';
    const string ACCESS_API_IP = 'access_api_ip';
    const string AML_BOT_FLOW_MODE = 'aml_bot_flow_mode';

    const string AI_AML_CHECK_ENABLED = 'ai_aml_check_enabled';

    /**
     * Automatic sending of funds for "payout" (sell) operations
     */
    const string AUTO_SEND_SELL_ENABLED = 'auto_send_sell_enabled';

    protected $guarded = [];

    /**
     * TRX amount for sending USDT (Fee Limit)
     * @return float
     */
    public static function getTrxAmount(): float
    {
        return (float)self::getValueByKey(self::TRX_AMOUNT, 30);
    }

    public static function getCountEnergy(): float
    {
        return (float)self::getValueByKey(self::COUNT_ENERGY_SEND_TR, Wallet::ENERGY_WALLET_FOR_ACTIVATE);
    }

    /**
     * Amount from which the AML check is triggered
     * @return float
     */
    public static function getAmlSumCheck(): float
    {
        return (float)self::getValueByKey(self::AML_SUM_CHECK, 101);
    }

    public static function getAlertEnergyMinSumCheck(): float
    {
        return (float)self::getValueByKey(self::ALERT_ENERGY_MIN_SUM_CHECK, 20);
    }

    public static function getCountTrxForActivateWallet(): float
    {
        return (float)self::getValueByKey(self::COUNT_TRX_FOR_ACTIVATE_WALLET, 3);
    }

    public static function getMinTransactionAmount(): float
    {
        return (float)self::getValueByKey(self::MINIMUM_TR_AMOUNT, 2);
    }

    public static function getAmlBotFlowMode(): string
    {
        return self::getValueByKey(self::AML_BOT_FLOW_MODE, 'fast', true);
    }

    /**
     * Whether AI verification of AML results is enabled
     */
    public static function isAiAmlCheckEnabled(): bool
    {
        return (bool) ((int) self::getValueByKey(self::AI_AML_CHECK_ENABLED, 0, true));
    }

    /**
     * Whether automatic sending of funds for "payout" (sell) operations is enabled
     */
    public static function isAutoSendSellEnabled(): bool
    {
        return (bool)((int)self::getValueByKey(self::AUTO_SEND_SELL_ENABLED, 0, true));
    }

    /**
     * @deprecated
     * @return array
     */
    public static function getTelegramUsers(): array
    {
        $ids = self::getValueByKey(self::TELEGRAM_USERS_ID, '', true);
        return ArrayHelper::explode($ids, [',', '|']);
    }

    public static function getOperationsBotTelegramUsers(): array
    {
        $ids = self::getValueByKey(self::OPERATIONS_BOT_TELEGRAM_USERS_ID, '', true);
        return ArrayHelper::explode($ids, [',', '|']);
    }

    public static function getAccessApiIps(): array
    {
        $ips = self::getValueByKey(self::ACCESS_API_IP, '');
        return ArrayHelper::explode($ips, [',', '|']);
    }

    private static function getValueByKey(string $key, mixed $defaultValue = null, bool $refresh = false)
    {
        if (isset(self::$data[$key]) && !$refresh) {
            return self::$data[$key];
        }

        $data = self::query()->where('key', $key)->first();
        return self::$data[$key] = ($data->value ?? $defaultValue);
    }

    /**
     * @return int
     */
    public static function getCryptoConnect(): int
    {
        return Wallet::CRYPTO_DEFAULT_CONNECT;
    }

}
