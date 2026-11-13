<?php

namespace App\Services\Aml\Services;

use App\Jobs\Aml\CoinKyt\AmlAddressCheck;
use App\Jobs\Aml\CoinKyt\AmlTransactionCheck;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Services\Aml\Contracts\AmlServiceInterface;
use App\Services\Aml\CoinKyt\GetReportUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinKytService implements AmlServiceInterface
{
    public function getCode(): string
    {
        return AmlService::COINKYT_CODE;
    }

    public function getName(): string
    {
        return 'CoinKYT';
    }

    public function supportsBlockchain(int $blockchainType): bool
    {
        return in_array($blockchainType, $this->getSupportedBlockchains(), true);
    }

    public function supportsRecipients(string $recipientExtCode): bool
    {
        // Empty list = service available to all recipients
        $allowed = (array) config('services.coinkyt.recipients', []);

        return !$allowed || in_array($recipientExtCode, $allowed, true);
    }

    public function checkAddress(AmlAddress $amlAddress): void
    {
        AmlAddressCheck::dispatch($amlAddress);
    }

    public function checkTransaction(Transaction $transaction): void
    {
        if (!$this->supportsBlockchain($transaction->wallet->blockchain_type)) {
            return;
        }

        AmlTransactionCheck::dispatch($transaction->id);
    }

    public function downloadReport(?string $directLink, ?array $response): mixed
    {
        // The check UUID is stored in the id field of the CoinKYT response
        $uuid = $response['id'] ?? null;

        if (!$uuid) {
            return response()->json(['error' => 'Check UUID is missing'], 400);
        }

        $language = config('services.coinkyt.report_language', 'en');

        // Step 1: get the signed PDF link
        $urlResult = (new GetReportUrl())->get($uuid, $language);

        if (isset($urlResult['error']) || empty($urlResult['report_url'])) {
            Log::channel('aml')->error('CoinKYT: failed to get report link', [
                'uuid' => $uuid,
                'result' => $urlResult,
            ]);
            return response()->json(['error' => 'Failed to get report link'], 500);
        }

        $reportUrl = $urlResult['report_url'];

        // Step 2: download the PDF via the signed link (token is in the URL)
        $pdfResponse = Http::get($reportUrl);

        if ($pdfResponse->successful()) {
            return response()->streamDownload(function () use ($pdfResponse) {
                echo $pdfResponse->body();
            }, "coinkyt_aml_report_{$uuid}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        }

        Log::channel('aml')->error('CoinKYT: failed to download PDF report', [
            'uuid' => $uuid,
            'report_url' => $reportUrl,
            'status' => $pdfResponse->status(),
        ]);

        return response()->json(['error' => 'Failed to download file'], 500);
    }

    public function getSupportedBlockchains(): array
    {
        return [
//"btc" - Bitcoin network;
//"eth" - Ethereum network;
//"trx" - Tron network.

            AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
            AmlAddress::BLOCKCHAIN_TYPE_BTC,
            AmlAddress::BLOCKCHAIN_TYPE_ETH,
        ];
    }


    /**
     * Extracts signals from the CoinKYT response.
     * Key - entity type (SCAM, EXCHANGE_LICENSED, etc.), value - share (0-1).
     */
    public function extractSignals(array $response): array
    {
        return collect($response['indirects'] ?? [])
            ->mapWithKeys(fn($item) => [$item['type'] => (float) ($item['total_count_coef'] ?? 0)])
            ->filter(fn(float $coef) => $coef >= 0.01)
            ->all();
    }

    /**
     * Extracts counterparties from the CoinKYT response.
     */
    public function extractCounterparty(array $response): array
    {
        return array_merge(
            $response['direct_entities'] ?? [],
            $response['from_entity'] ?? [],
            $response['to_entity'] ?? [],
        );
    }

    public function getSignalGroups(): array
    {
        return [
            'high' => [
                'SCAM', 'SCAM_CRYPTO_EXCHANGE', 'TERRORISM_FINANCING', 'SANCTIONS',
                'RANSOM', 'DARKNET_MARKETPLACE', 'DARKNET_SERVICE', 'ILLEGAL_SERVICE',
                'MIXING_SERVICE', 'STOLEN_ASSETS', 'MALWARE', 'CHILD_ABUSE_MATERIAL',
                'ILLEGAL_ARMS_TRAFFICKING', 'DRUGS_TRAFFICKING', 'EXTREMISM',
                'SANCTIONED_JURISDICTION', 'GAMBLING',
            ],
            'middle' => [
                'EXCHANGE_UNLICENSED', 'P2P_EXCHANGE_UNLICENSED',
                'PRIVACY_PROTOCOL', 'BRIDGES', 'SEIZURE_OF_FUNDS',
            ],
            'low' => [
                'EXCHANGE_LICENSED', 'P2P_EXCHANGE_LICENSED', 'ONLINE_WALLET', 'MINER',
                'REWARDS_FEES', 'ATM', 'PAYMENT_SYSTEM', 'DECENTRALIZED_EXCHANGE',
                'LENDING_PROTOCOL', 'OTHER', 'UNKNOWN_OWNER',
                'ONLINE_MARKETPLACE', 'SEIZED_ASSETS',
            ],
        ];
    }

    public static function isEnabledService(): bool
    {
        /**
         * @var AmlService $service
         */
        $service = AmlService::coinKyt()->first();
        return $service->is_enabled;
    }

}
