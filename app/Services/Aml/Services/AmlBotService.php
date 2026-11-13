<?php

namespace App\Services\Aml\Services;

use App\Jobs\Aml\AmlBot\AmlAddressCheck;
use App\Jobs\Aml\AmlBot\AmlTransactionCheck;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Repositories\TransactionRepository;
use App\Repositories\WalletRepository;
use App\Services\Aml\AmlChecksManager;
use App\Services\Aml\AmlReportStorage;
use App\Services\Aml\AmlBot\Aml;
use App\Services\Aml\Contracts\AmlServiceInterface;
use Illuminate\Support\Facades\Http;

class AmlBotService implements AmlServiceInterface
{
    public function getCode(): string
    {
        return AmlService::AML_BOT_CODE;
    }

    public function getName(): string
    {
        return 'AML Bot';
    }

    public function supportsBlockchain(int $blockchainType): bool
    {
        // AmlBot supports every blockchain the system supports
        return true;
    }

    public function supportsRecipients(string $recipientExtCode): bool
    {
        return true;
    }

    public function checkAddress(AmlAddress $amlAddress): void
    {
        if (Aml::isModeInstant()) {
            AmlAddressCheck::dispatchSync($amlAddress);
        } else {
            AmlAddressCheck::dispatch($amlAddress);
        }
    }

    public function checkTransaction(Transaction $transaction): void
    {
        if (Aml::isModeInstant()) {
            AmlTransactionCheck::dispatchSync($transaction->id);
        } else {
            AmlTransactionCheck::dispatch($transaction->id);
        }
    }

    public function downloadReport(?string $directLink, ?array $response): mixed
    {
        if (!$directLink) {
            return response()->json(['error' => 'Report link is missing'], 400);
        }

        $httpResponse = Http::get($directLink);

        if ($httpResponse->successful()) {
            return response()->streamDownload(function () use ($httpResponse) {
                echo $httpResponse->body();
            }, basename($directLink) . '.pdf');
        }

        return response()->json(['error' => 'Failed to download file'], 500);
    }

    public function getSupportedBlockchains(): array
    {
        return [
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
            AmlAddress::BLOCKCHAIN_TYPE_BTC,
            AmlAddress::BLOCKCHAIN_TYPE_ETH,
            AmlAddress::BLOCKCHAIN_TYPE_BCH,
            AmlAddress::BLOCKCHAIN_TYPE_LTC,
            AmlAddress::BLOCKCHAIN_TYPE_XRP,
            AmlAddress::BLOCKCHAIN_TYPE_ETC,
            AmlAddress::BLOCKCHAIN_TYPE_ADA,
            AmlAddress::BLOCKCHAIN_TYPE_ALGO,
            AmlAddress::BLOCKCHAIN_TYPE_XLM,
            AmlAddress::BLOCKCHAIN_TYPE_MATIC,
            AmlAddress::BLOCKCHAIN_TYPE_OP,
            AmlAddress::BLOCKCHAIN_TYPE_BASE,
            AmlAddress::BLOCKCHAIN_TYPE_ZEC,
            AmlAddress::BLOCKCHAIN_TYPE_DOGE,
            AmlAddress::BLOCKCHAIN_TYPE_SOLANA,
            AmlAddress::BLOCKCHAIN_TYPE_BSV,
            AmlAddress::BLOCKCHAIN_TYPE_AVAX,
            AmlAddress::BLOCKCHAIN_TYPE_DOT,
            AmlAddress::BLOCKCHAIN_TYPE_TON,
            AmlAddress::BLOCKCHAIN_TYPE_XTZ,
            AmlAddress::BLOCKCHAIN_TYPE_TETHER_OMNI,
            AmlAddress::BLOCKCHAIN_TYPE_NEAR,
            AmlAddress::BLOCKCHAIN_TYPE_BSC,
            AmlAddress::BLOCKCHAIN_TYPE_APT,
            AmlAddress::BLOCKCHAIN_TYPE_ARB,
        ];
    }

    /**
     * Extracts counterparties from the AmlBot response.
     * Takes incoming source signals (signals.in), filters out zeros
     * and converts them to a simple format ['Exchange' => 53.1, ...]
     */
    public function extractCounterparty(array $response): array
    {
        $inSignals = $response['counterparty']['signals']['in'] ?? [];

        return collect($inSignals)
            ->filter(fn($value) => $value > 0)
            ->mapWithKeys(fn($value, $key) => [
                ucwords(str_replace('_', ' ', $key)) => round($value * 100, 2),
            ])
            ->all();
    }

    public function getSignalGroups(): array
    {
        return [
            'low' => [
                'merchant_services', 'ico', 'exchange', 'trusted_exchange', 'wallet',
                'miner', 'payment', 'marketplace', 'exchange_mlrisk_low',
                'p2p_exchange_mlrisk_low', 'p2p_exchange', 'seized_assets', 'other',
                'bridge', 'nft_platform_collection',
            ],
            'middle' => [
                'infrastructure_as_a_service', 'decentralized_exchange_contract',
                'smart_contract', 'token_smart_contract', 'lending_contract',
                'protocol_privacy', 'unnamed_service', 'atm', 'risky_exchange',
                'exchange_mlrisk_moderate', 'exchange_mlrisk_high',
                'exchange_mlrisk_veryhigh', 'p2p_exchange_mlrisk_high', 'liquidity_pools',
                'high_risk_jurisdiction', 'special_measures',
            ],
            'high' => [
                'fraud_shop', 'illicit_actor_org',
                'enforcement_action', 'ransom', 'dark_market', 'dark_service',
                'illegal_service', 'mixer', 'scam', 'gambling', 'stolen_coins',
                'exchange_fraudulent', 'child_exploitation', 'sanctions',
                'terrorism_financing', 'online_pharmacy', 'malware',
            ],
        ];
    }

    public static function setTransactionRiskOnlyAmlBot(Transaction $transaction, array $response): void
    {
        $riscScore = $response['riskscore'] ?? null;
        $report = $response['pdfReport'] ?? '';
        $signals = $response['signals'] ?? [];
        $counterparty = (new self())->extractCounterparty($response);

        if (is_null($riscScore)) {
            return;
        }

        app(TransactionRepository::class)->updateAmlRiskScore($transaction, $riscScore, $report);

        $amlService = AmlService::amlBot()->first();

        $freshTransaction = $transaction->fresh();
        $localReportPath = null;
        if ($report) {
            $localReportPath = app(AmlReportStorage::class)->downloadAndStore(
                $report,
                "aml_reports/amlbot/transaction/{$freshTransaction->id}.pdf",
            );
        }

        $check = $freshTransaction->addCheckToHistory(
            $amlService->id,
            $riscScore,
            $report,
            $response['uid'] ?? null,
            $response,
            $signals,
            $counterparty,
        );

        if ($localReportPath) {
            $check->update(['local_report_path' => $localReportPath]);
        }

        app(AmlChecksManager::class)->checkAndFinalizeTransaction($freshTransaction->fresh());
    }

    public static function isEnabledService():bool
    {
        /**
         * @var AmlService $service
         */
        $service = AmlService::amlBot()->first();
        return $service->is_enabled;
    }
}
