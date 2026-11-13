<?php

/**
 * @site https://docs.amlbot.com/webApi/requests#aml-transaction-verification-1
 */

namespace App\Models\Aml;

use App\Enums\AmlAddressStatuses;
use App\Models\Aml\AmlService;
use App\Models\BaseModel;
use App\Models\Wallet\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

/**
 * @property int $id
 * @property int $has_error
 * @property int $status
 * @property int $blockchain_type
 * @property string $address
 * @property string $comment
 * @property string $error
 * @property string $current_transaction_hash
 * @property string $last_check_transaction_hash
 * @property string $current_trx_transaction_hash
 * @property string $last_check_trx_transaction_hash
 * @property float $aml_risk_score
 * @property string $aml_file_report_link
 * @property string $aml_uid
 * @property array $response
 * @property int $current_tx_count
 * @property int $last_check_tx_count
 * @property int $is_test
 * @property float $aml_balance
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $check_finish_at
 * @property Carbon $last_check_hash_at
 * @property string $aml_code
 * @property string $status_name
 * @property string $explorer_address_link
 * @property string $blockchain_text
 * @property string $well_wallet_network
 * @property AmlAddressCheck[] $checks
 */
class AmlAddress extends BaseModel
{
    use HasFactory, AsSource, Filterable;

    const string ETH_REGEXP = '/^0x[a-fA-F0-9]{40}$/';

    public const int BLOCKCHAIN_TYPE_TRC_20 = 1;
    public const int BLOCKCHAIN_TYPE_BTC = 2;
    public const int BLOCKCHAIN_TYPE_ETH = 3;
    public const int BLOCKCHAIN_TYPE_BCH = 4;
    public const int BLOCKCHAIN_TYPE_LTC = 5;
    public const int BLOCKCHAIN_TYPE_XRP = 6;
    public const int BLOCKCHAIN_TYPE_ETC = 7;
    public const int BLOCKCHAIN_TYPE_ADA = 8;
    public const int BLOCKCHAIN_TYPE_ALGO = 9;
    public const int BLOCKCHAIN_TYPE_XLM = 10;
    public const int BLOCKCHAIN_TYPE_MATIC = 11;
    public const int BLOCKCHAIN_TYPE_OP = 12;
    public const int BLOCKCHAIN_TYPE_BASE = 13;
    public const int BLOCKCHAIN_TYPE_ZEC = 14;
    public const int BLOCKCHAIN_TYPE_DOGE = 15;
    public const int BLOCKCHAIN_TYPE_SOLANA = 16;
    public const int BLOCKCHAIN_TYPE_BSV = 17;
    public const int BLOCKCHAIN_TYPE_AVAX = 18;
    public const int BLOCKCHAIN_TYPE_DOT = 19;
    public const int BLOCKCHAIN_TYPE_TON = 20;
    public const int BLOCKCHAIN_TYPE_XTZ = 21;
    public const int BLOCKCHAIN_TYPE_TETHER_OMNI = 22;
    public const int BLOCKCHAIN_TYPE_NEAR = 23;
    public const int BLOCKCHAIN_TYPE_BSC = 24;
    public const int BLOCKCHAIN_TYPE_APT = 25;
    public const int BLOCKCHAIN_TYPE_ARB = 26;

// TRX,
// BTC,
// ETH,

// BCH,
// LTC,
// XRP,
// ETC,
// ADA,
// ALGO,
// XLM,
// MATIC,
// OP,
// BASE,
// ZEC,
// DOGE,
// SOLANA,
// BSV,
// AVAX,
// DOT,
// TON,
// XTZ,
// TetherOMNI or NEAR
// BSC
// ARB


//(BTC, BCH, LTC, XRP, ETC, ADA, ETH, TRX, ALGO, XLM, MATIC, OP, BASE, ZEC, DOGE, SOLANA, BSV, AVAX, DOT, TON, XTZ, TetherOMNI, NEAR or APT)
//For tokens verify:
//ERC20 tokens, please use in your request: asset = ETH
//BEP20 tokens, please use in your request: asset = BSC
//Polygon ERC-20 tokens, please use in your request: asset = MATIC
//TRC20 tokens, please use in your request: asset = TRX
//Ripple tokens, please use in your request: asset = XRP
//Arbitrum ERC-20 tokens, please use in your request: asset = ARB
//XLM tokens, please use in your request: asset = XLM
//To verify Optimism ERC20 tokens, please use in your request: asset = OP
//To verify Base ERC20 tokens, please use in your request: asset = BASE
//To verify Avalanche ERC-20 tokens, please use in your request: asset = AVAX
//To verify TON tokens, please use in your request: asset = TON
//To verify Aptos tokens, please use in your request: asset = APT


    protected $guarded = [];

    protected $casts = [
        'check_finish_at' => 'datetime',
        'last_check_hash_at' => 'datetime',
        'response' => 'array',
    ];


    /**
     * aml_code
     * @return string
     */
    public function getAmlCodeAttribute(): string
    {
        return self::amlCodes()[$this->blockchain_type] ?? '';
    }

    /**
     * status_name
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return AmlAddressStatuses::options()[$this->status] ?? '-';
    }

    public function checks(): HasMany
    {
        return $this->hasMany(AmlAddressCheck::class)->oldest();
    }

    /**
     * Returns the check record for a specific AML service by its code.
     * Used when the uid/hash of a specific provider is needed.
     */
    public function checksForService(string $serviceCode): ?AmlAddressCheck
    {
        $serviceId = AmlService::where('code', $serviceCode)->value('id');
        if (!$serviceId) {
            return null;
        }

        return $this->checks()->where('aml_service_id', $serviceId)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|AmlAddressCheck
     */
    public function addCheckToHistory(int $amlServiceId, array $counterparty = []): \Illuminate\Database\Eloquent\Model
    {
        $signals = [];
        if (isset($this->response['data']['signals'])) {
            $signals = $this->formatSignals($this->response['data']['signals']);
        }

        return $this->checks()->updateOrCreate(
            ['aml_service_id' => $amlServiceId],
            [
                'status' => AmlAddressCheck::STATUS_COMPLETED,
                'error' => $this->error,
                'current_transaction_hash' => $this->current_transaction_hash,
                'last_check_transaction_hash' => $this->last_check_transaction_hash,
                'current_trx_transaction_hash' => $this->current_trx_transaction_hash ?? null,
                'last_check_trx_transaction_hash' => $this->last_check_trx_transaction_hash ?? null,
                'aml_risk_score' => $this->aml_risk_score,
                'aml_file_report_link' => $this->aml_file_report_link,
                'response' => $this->response,
                'signals' => $signals,
                'counterparty' => $counterparty,
                'current_tx_count' => $this->current_tx_count,
                'check_finish_at' => $this->check_finish_at,
                'aml_balance' => $this->aml_balance,
            ]
        );
    }

    /**
     * Get the latest AML checks of the address grouped by service
     * @return \Illuminate\Support\Collection
     */
    public function getLatestAmlChecks(): \Illuminate\Support\Collection
    {
        return $this->checks()
            ->with('amlService')
            ->get()
            ->groupBy('amlService.code')
            ->map(function ($checks) {
                return $checks->sortByDesc('check_finish_at')->first();
            });
    }

    /**
     * @param array $signals
     * @return array
     */
    private function formatSignals(array $signals): array
    {
        return \App\Models\Wallet\Wallet::formatSignals($signals);
    }


    public function isProcess(): bool
    {
        return $this->status === AmlAddressStatuses::PROCESS->value;
    }

    public function isTrc20(): bool
    {
        return $this->blockchain_type === self::BLOCKCHAIN_TYPE_TRC_20;
    }

    public function isNewTransactions(): bool
    {
        return self::isNewByHash(
            $this->current_transaction_hash,
            $this->last_check_transaction_hash,
            $this->current_trx_transaction_hash,
            $this->last_check_trx_transaction_hash
        );
    }

    public static function isNewByHash(
        ?string $currentTransactionHash,
        ?string $lastCheckTransactionHash,
        ?string $currentTrxTransactionHash,
        ?string $lastCheckTrxTransactionHash,
    ): bool
    {
        // check usdt if there is a current hash and it differs from the previous one (or there is no previous one)
        $hasNewUsdt = $currentTransactionHash &&
            ($currentTransactionHash !== $lastCheckTransactionHash || !$lastCheckTransactionHash);

        // check trx if there is a current hash and it differs from the previous one (or there is no previous one)
        $hasNewTrx = $currentTrxTransactionHash &&
            ($currentTrxTransactionHash !== $lastCheckTrxTransactionHash || !$lastCheckTrxTransactionHash);

        return $hasNewUsdt || $hasNewTrx;
    }

    public static function amlCodes(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => 'TRX',
            self::BLOCKCHAIN_TYPE_BTC => 'BTC',
            self::BLOCKCHAIN_TYPE_ETH => 'ETH',
            self::BLOCKCHAIN_TYPE_BCH => 'BCH',
            self::BLOCKCHAIN_TYPE_LTC => 'LTC',
            self::BLOCKCHAIN_TYPE_XRP => 'XRP',
            self::BLOCKCHAIN_TYPE_ETC => 'ETC',
            self::BLOCKCHAIN_TYPE_ADA => 'ADA',
            self::BLOCKCHAIN_TYPE_ALGO => 'ALGO',
            self::BLOCKCHAIN_TYPE_XLM => 'XLM',
            self::BLOCKCHAIN_TYPE_MATIC => 'MATIC',
            self::BLOCKCHAIN_TYPE_OP => 'OP',
            self::BLOCKCHAIN_TYPE_BASE => 'BASE',
            self::BLOCKCHAIN_TYPE_ZEC => 'ZEC',
            self::BLOCKCHAIN_TYPE_DOGE => 'DOGE',
            self::BLOCKCHAIN_TYPE_SOLANA => 'SOLANA',
            self::BLOCKCHAIN_TYPE_BSV => 'BSV',
            self::BLOCKCHAIN_TYPE_AVAX => 'AVAX',
            self::BLOCKCHAIN_TYPE_DOT => 'DOT',
            self::BLOCKCHAIN_TYPE_TON => 'TON',
            self::BLOCKCHAIN_TYPE_XTZ => 'XTZ',
            self::BLOCKCHAIN_TYPE_TETHER_OMNI => 'TETHER-OMNI',
            self::BLOCKCHAIN_TYPE_NEAR => 'NEAR',
            self::BLOCKCHAIN_TYPE_BSC => 'BSC',
            self::BLOCKCHAIN_TYPE_APT => 'APT',
            self::BLOCKCHAIN_TYPE_ARB => 'ARB',
        ];
    }

    public static function blockChainTypesNames(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => 'trc-20',
            self::BLOCKCHAIN_TYPE_BTC => 'btc',
            self::BLOCKCHAIN_TYPE_ETH => 'eth',
            self::BLOCKCHAIN_TYPE_BCH => 'bch',
            self::BLOCKCHAIN_TYPE_LTC => 'ltc',
            self::BLOCKCHAIN_TYPE_XRP => 'xrp',
            self::BLOCKCHAIN_TYPE_ETC => 'etc',
            self::BLOCKCHAIN_TYPE_ADA => 'ada',
            self::BLOCKCHAIN_TYPE_ALGO => 'algo',
            self::BLOCKCHAIN_TYPE_XLM => 'xlm',
            self::BLOCKCHAIN_TYPE_MATIC => 'matic',
            self::BLOCKCHAIN_TYPE_OP => 'op',
            self::BLOCKCHAIN_TYPE_BASE => 'base',
            self::BLOCKCHAIN_TYPE_ZEC => 'zec',
            self::BLOCKCHAIN_TYPE_DOGE => 'doge',
            self::BLOCKCHAIN_TYPE_SOLANA => 'solana',
            self::BLOCKCHAIN_TYPE_BSV => 'bsv',
            self::BLOCKCHAIN_TYPE_AVAX => 'avax',
            self::BLOCKCHAIN_TYPE_DOT => 'dot',
            self::BLOCKCHAIN_TYPE_TON => 'ton',
            self::BLOCKCHAIN_TYPE_XTZ => 'xtz',
            self::BLOCKCHAIN_TYPE_TETHER_OMNI => 'tether-omni',
            self::BLOCKCHAIN_TYPE_NEAR => 'near',
            self::BLOCKCHAIN_TYPE_BSC => 'bsc',
            self::BLOCKCHAIN_TYPE_APT => 'apt',
            self::BLOCKCHAIN_TYPE_ARB => 'arb',
        ];
    }

    public static function blockChainTypesRegexp(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
            self::BLOCKCHAIN_TYPE_BTC => '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$|^(bc1)[0-9A-Za-z]{25,39}$/',
            self::BLOCKCHAIN_TYPE_ETH => self::ETH_REGEXP,
            self::BLOCKCHAIN_TYPE_BCH => '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$|^(bitcoincash:)?[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{42}$/',
            self::BLOCKCHAIN_TYPE_LTC => '/^[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}$/',
            self::BLOCKCHAIN_TYPE_XRP => '/^r[0-9a-zA-Z]{24,34}$/',
            self::BLOCKCHAIN_TYPE_ETC => self::ETH_REGEXP,
            self::BLOCKCHAIN_TYPE_ADA => '/^addr1[0-9a-zA-Z]{98}$/',
            self::BLOCKCHAIN_TYPE_ALGO => '/^[A-Z2-7]{58}$/',
            self::BLOCKCHAIN_TYPE_XLM => '/^G[A-Z2-7]{55}$/',
            self::BLOCKCHAIN_TYPE_MATIC => self::ETH_REGEXP,
            self::BLOCKCHAIN_TYPE_OP => self::ETH_REGEXP,
            self::BLOCKCHAIN_TYPE_BASE => self::ETH_REGEXP,
            self::BLOCKCHAIN_TYPE_ZEC => '/^t[13][a-zA-Z0-9]{33}$/',
            self::BLOCKCHAIN_TYPE_DOGE => '/^D{1}[5-9A-HJ-NP-U]{1}[1-9A-HJ-NP-Za-km-z]{32}$/',
            self::BLOCKCHAIN_TYPE_SOLANA => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
            self::BLOCKCHAIN_TYPE_BSV => '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/',
            self::BLOCKCHAIN_TYPE_AVAX => '/^X-[a-zA-Z0-9]{39}$/',
            self::BLOCKCHAIN_TYPE_DOT => '/^[1-9A-HJ-NP-Za-km-z]{47,48}$/',
            self::BLOCKCHAIN_TYPE_TON => '/^[0-9A-Za-z-_]{48}$/',
            self::BLOCKCHAIN_TYPE_XTZ => '/^tz[1-3][1-9A-HJ-NP-Za-km-z]{33}$/',
            self::BLOCKCHAIN_TYPE_TETHER_OMNI => '/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/',
            self::BLOCKCHAIN_TYPE_NEAR => '/^[a-z0-9_-]{2,64}\.near$/',
            self::BLOCKCHAIN_TYPE_BSC => self::ETH_REGEXP,
            self::BLOCKCHAIN_TYPE_APT => '/^[a-f0-9]{64}$/i',
            self::BLOCKCHAIN_TYPE_ARB => self::ETH_REGEXP,
        ];
    }

    public static function blockChainExplorers(): array
    {
        return [
            self::BLOCKCHAIN_TYPE_TRC_20 => 'https://tronscan.org/#/address/',
            self::BLOCKCHAIN_TYPE_BTC => 'https://www.blockchain.com/explorer/addresses/btc/',
            self::BLOCKCHAIN_TYPE_ETH => 'https://etherscan.io/address/',
            self::BLOCKCHAIN_TYPE_BCH => 'https://www.blockchain.com/explorer/addresses/bch/',
            self::BLOCKCHAIN_TYPE_LTC => 'https://blockchair.com/litecoin/address/',
            self::BLOCKCHAIN_TYPE_XRP => 'https://xrpscan.com/account/',
            self::BLOCKCHAIN_TYPE_ETC => 'https://blockscout.com/etc/mainnet/address/',
            self::BLOCKCHAIN_TYPE_ADA => 'https://cardanoscan.io/address/',
            self::BLOCKCHAIN_TYPE_ALGO => 'https://algoexplorer.io/address/',
            self::BLOCKCHAIN_TYPE_XLM => 'https://stellar.expert/explorer/public/account/',
            self::BLOCKCHAIN_TYPE_MATIC => 'https://polygonscan.com/address/',
            self::BLOCKCHAIN_TYPE_OP => 'https://optimistic.etherscan.io/address/',
            self::BLOCKCHAIN_TYPE_BASE => 'https://basescan.org/address/',
            self::BLOCKCHAIN_TYPE_ZEC => 'https://explorer.zcha.in/accounts/',
            self::BLOCKCHAIN_TYPE_DOGE => 'https://dogechain.info/address/',
            self::BLOCKCHAIN_TYPE_SOLANA => 'https://solscan.io/account/',
            self::BLOCKCHAIN_TYPE_BSV => 'https://whatsonchain.com/address/',
            self::BLOCKCHAIN_TYPE_AVAX => 'https://snowtrace.io/address/',
            self::BLOCKCHAIN_TYPE_DOT => 'https://polkadot.subscan.io/account/',
            self::BLOCKCHAIN_TYPE_TON => 'https://tonscan.org/address/',
            self::BLOCKCHAIN_TYPE_XTZ => 'https://tzstats.com/',
            self::BLOCKCHAIN_TYPE_TETHER_OMNI => 'https://www.omniexplorer.info/address/',
            self::BLOCKCHAIN_TYPE_NEAR => 'https://explorer.near.org/accounts/',
            self::BLOCKCHAIN_TYPE_BSC => 'https://bscscan.com/address/',
            self::BLOCKCHAIN_TYPE_APT => 'https://explorer.aptoslabs.com/account/',
            self::BLOCKCHAIN_TYPE_ARB => 'https://arbiscan.io/address/',
        ];
    }

    public static function getBlockCodes(): void
    {
        $codes = [
            'BTC' => 'BTC',
            'BCH' => 'BCH',
            'BSV' => 'BSV',
            'ETH' => 'ETH',
            'ETC' => 'ETC',
            'LTC' => 'LTC',
            'TRX' => 'TRX',
            'XRP' => 'XRP',
            'XLM' => 'XLM',
            'BSC' => 'BSC',
            'MATIC' => 'MATIC',
            'USDTERC20' => 'USDTERC20',
            'USDTTRC20' => 'USDTTRC20',
            'USDCTRC20' => 'USDCTRC20',
            'USDTBEP20' => 'USDTBEP20',
            'USDCBEP20' => 'USDCBEP20',
        ];
    }


    /**
     * explorer_address_link
     * @return string
     */
    public function getExplorerAddressLinkAttribute(): string
    {
        $explorer = self::blockChainExplorers()[$this->blockchain_type] ?? '';
        return $explorer ? ($explorer . $this->address) : '';
    }

    /**
     * Get the name by type
     * blockchain_text
     * @return string
     */
    public function getBlockchainTextAttribute(): string
    {
        return self::blockChainTypesNames()[$this->blockchain_type] ?? '-';
    }

    /**
     * Get the blockchain type by name
     * @param string $blockChainName
     * @return int|null
     */
    public static function getBlockChainTypeByName(string $blockChainName): ?int
    {
        $blockchainTypes = array_flip(self::blockChainTypesNames());
        return $blockchainTypes[$blockChainName] ?? null;
    }

    public function isWellWalletCheck(): true
    {
        //trx,eth,btc,sol,bnb
        return in_array($this->blockchain_type, [
            self::BLOCKCHAIN_TYPE_TRC_20,
            self::BLOCKCHAIN_TYPE_ETH,
            self::BLOCKCHAIN_TYPE_BTC,
            self::BLOCKCHAIN_TYPE_SOLANA,
        ], true);
    }

    /**
     * well_wallet_network
     * @return string
     */
    public function getWellWalletNetworkAttribute(): string
    {
        //trx,eth,btc,sol,bnb
        return match ($this->blockchain_type) {
            self::BLOCKCHAIN_TYPE_SOLANA => 'sol',
            default => strtolower($this->aml_code),
        };
    }
}
