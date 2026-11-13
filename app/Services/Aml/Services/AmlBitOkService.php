<?php

namespace App\Services\Aml\Services;

use App\Jobs\Aml\AmlBitOk\AmlAddressCheck;
use App\Jobs\Aml\AmlBitOk\AmlTransactionCheck;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Services\Aml\Contracts\AmlServiceInterface;
use Illuminate\Support\Facades\Http;

class AmlBitOkService implements AmlServiceInterface
{
    public function getCode(): string
    {
        return AmlService::AML_BITOK_CODE;
    }

    public function getName(): string
    {
        return 'BitOK AML';
    }

    public function supportsBlockchain(int $blockchainType): bool
    {
        return in_array($blockchainType, $this->getSupportedBlockchains(), true);
    }

    public function supportsRecipients(string $recipientExtCode): bool
    {
        return true;
    }

    public function checkAddress(AmlAddress $amlAddress): void
    {
        AmlAddressCheck::dispatch($amlAddress);
    }

    public function checkTransaction(Transaction $transaction): void
    {
        // Check whether BitOK supports this blockchain
        if (!$this->supportsBlockchain($transaction->wallet->blockchain_type)) {
            return;
        }

        AmlTransactionCheck::dispatch($transaction->id);
    }

    public function downloadReport(?string $directLink, ?array $response): mixed
    {
        if (!$directLink) {
            return response()->json(['error' => 'Report link is missing'], 400);
        }

        // BitOK requires authentication to download reports
        $timestamp = time() * 1000;
        $signature = $this->generateSignature('GET', $directLink, $timestamp);

        $httpResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'API-KEY-ID' => config('services.aml_bitok.key_id'),
            'API-TIMESTAMP' => $timestamp,
            'API-SIGNATURE' => $signature,
        ])->get($directLink);

        if ($httpResponse->successful()) {
            return response()->streamDownload(function () use ($httpResponse) {
                echo $httpResponse->body();
            }, 'bitok_aml_report.pdf');
        }

        return response()->json(['error' => 'Failed to download file'], 500);
    }

    /**
     * BitOK supports the major blockchains
     * @return array
     */
    public function getSupportedBlockchains(): array
    {
        return [
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
            AmlAddress::BLOCKCHAIN_TYPE_BTC,
            AmlAddress::BLOCKCHAIN_TYPE_ETH,
            AmlAddress::BLOCKCHAIN_TYPE_LTC,
            AmlAddress::BLOCKCHAIN_TYPE_DOGE,
            AmlAddress::BLOCKCHAIN_TYPE_BSC,
            AmlAddress::BLOCKCHAIN_TYPE_MATIC,
        ];
    }

    /**
     * Extracts signals from the Transfers/Risks::getRisks() response.
     * $response - array of risk records like [{entity_category, value_share, ...}, ...]
     */
    public function extractSignals(array $response): array
    {
        return collect($response)
            ->mapWithKeys(fn($item) => [$item['entity_category'] => (float) ($item['value_share'] ?? 0)])
            ->filter(fn(float $value) => $value > 0)
            ->all();
    }

    /**
     * Extracts counterparties from the BitOK response.
     */
    public function extractCounterparty(array $response): array
    {
        return [];
    }

    public function getSignalGroups(): array
    {
        return [
            'high' => [
                'sanctions', 'illegal_service', 'scam', 'stolen_funds', 'terrorist_financing',
                'online_pharmacy', 'ransom', 'darknet', 'malware', 'fraud',
                'child_abuse', 'arms_trafficking', 'drugs_trafficking', 'extortion',
                'gambling', 'mixer',
            ],
            'middle' => [
                'high_risk_exchange', 'p2p_exchange', 'bridge', 'ico',
                'privacy_coin', 'seizure',
            ],
            'low' => [
                'exchange', 'mining', 'mining_pool', 'lending', 'wallet',
                'payment', 'defi', 'other', 'unknown',
            ],
        ];
    }

    /**
     * Generates the signature for the BitOK API
     */
    private function generateSignature(string $method, string $url, int $timestamp, array $params = []): string
    {
        $urlPath = parse_url($url, PHP_URL_PATH);

        $strToSign = implode("\n", [
            strtoupper($method),
            $urlPath,
            $timestamp
        ]);

        if (!empty($params) && $method === 'POST') {
            $strToSign .= "\n" . json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $signature = hash_hmac(
            'sha256',
            $strToSign,
            config('services.aml_bitok.secret_key'),
            true
        );

        return base64_encode($signature);
    }

    public static function isEnabledService(): bool
    {
        /**
         * @var AmlService $service
         */
        $service = AmlService::amlBitOk()->first();
        return $service->is_enabled;
    }
}
