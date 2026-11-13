<?php

namespace App\Services\Tron;

use App\Models\Wallet\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TronscanService
{
    /**
     * @throws \Exception
     */
    public function getTransactions(Wallet $wallet, int $limit = 20): array
    {
        if (!$wallet->isTrc20()) {
            return [];
        }

        return $this->getTransactionsByAddress($wallet->address, $limit);
    }

    /**
     * @param string $address
     * @return bool
     */
    public function isHasUsdtTransactions(string $address): bool
    {
        return (bool)$this->getTransactionsByAddress($address, 1);
    }

    /**
     * @throws \Exception
     */
    public function getTransactionsByAddress(string $address, int $limit = 20): array
    {
        $params = [
            'limit' => $limit,
            'start' => 0,
            'sort' => '-timestamp',
            'count' => 'true',
            'filterTokenValue' => 0,
            'relatedAddress' => $address
        ];
        try {
            $url = "https://apilist.tronscan.org/api/filter/trc20/transfers";

            $response = Http::timeout(30)->get($url, $params);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            if (!isset($data['token_transfers']) || !is_array($data['token_transfers'])) {
                return [];
            }

            return $this->formatData($data['token_transfers']);

        } catch (\Exception $e) {
            Log::channel('tronscan')->error('TronscanService getTransactionsByAddress error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'address' => $address,
                'params' => $params,
            ]);

            throw new \Exception($e->getMessage());
        }
    }

    private function formatData(array $transfers): array
    {
        $result = [];

        foreach ($transfers as $transfer) {

            $contractAddress = $transfer['contract_address'] ?? null;
            if ($contractAddress !== Tron::USDT_TRC20_CONTRACT) {
                continue;
            }

            $result[] = [
                'transaction_id' => $transfer['transaction_id'] ?? '',
                'block_timestamp' => $transfer['block_ts'] ?? 0,
                'from' => $transfer['from_address'] ?? '',
                'to' => $transfer['to_address'] ?? '',
                'value' => $transfer['quant'] ?? '0',
                'type' => 'Transfer',
                'token_info' => [
                    'name' => $transfer['tokenInfo']['tokenName'] ?? '',
                    'symbol' => $transfer['tokenInfo']['tokenAbbr'] ?? '',
                    'decimals' => $transfer['tokenInfo']['tokenDecimal'] ?? 6,
                    'address' => $contractAddress,
                ]
            ];
        }

        return $result;
    }
}
