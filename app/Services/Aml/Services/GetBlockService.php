<?php

namespace App\Services\Aml\Services;

use App\Jobs\Aml\GetBlock\AmlAddressCheck;
use App\Jobs\Aml\GetBlock\AmlTransactionCheck;
use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlService;
use App\Models\Wallet\Transaction;
use App\Services\Aml\Contracts\AmlServiceInterface;
use Illuminate\Support\Facades\Http;

class GetBlockService implements AmlServiceInterface
{
    public const int RISC_SCORE_MULTIPLIER = 10;

    public function getCode(): string
    {
        return AmlService::GETBLOCK_CODE;
    }

    public function getName(): string
    {
        return 'GetBlock';
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
        // Check whether GetBlock supports this blockchain
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

        // GetBlock uses a direct link to the PDF report
        $httpResponse = Http::withHeaders([
        ])->get($directLink);

        if ($httpResponse->successful()) {
            return response()->streamDownload(function () use ($httpResponse) {
                echo $httpResponse->body();
            }, 'getblock_aml_report.pdf');
        }

        return response()->json(['error' => 'Failed to download file'], 500);
    }

    public function getSignalGroups(): array
    {
        return (new AmlBotService())->getSignalGroups();
    }

    /**
     * GetBlock supports most major blockchains
     * @return array
     */
    public function getSupportedBlockchains(): array
    {
        return [
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
            AmlAddress::BLOCKCHAIN_TYPE_BTC,
            AmlAddress::BLOCKCHAIN_TYPE_ETH,
            AmlAddress::BLOCKCHAIN_TYPE_BCH,
            AmlAddress::BLOCKCHAIN_TYPE_LTC,
            AmlAddress::BLOCKCHAIN_TYPE_DOGE,
            AmlAddress::BLOCKCHAIN_TYPE_SOLANA,
            AmlAddress::BLOCKCHAIN_TYPE_BSC,
            AmlAddress::BLOCKCHAIN_TYPE_MATIC,
            AmlAddress::BLOCKCHAIN_TYPE_OP,
            AmlAddress::BLOCKCHAIN_TYPE_BASE,
            AmlAddress::BLOCKCHAIN_TYPE_ARB,
            AmlAddress::BLOCKCHAIN_TYPE_AVAX,
        ];
    }

    public static function isEnabledService(): bool
    {
        /**
         * @var AmlService $service
         */
        $service = AmlService::getBlock()->first();
        return $service->is_enabled;
    }
}
