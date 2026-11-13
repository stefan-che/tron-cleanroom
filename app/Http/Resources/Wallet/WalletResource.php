<?php

namespace App\Http\Resources\Wallet;

use App\Helpers\AiAmlFormatter;
use App\Helpers\Development;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class WalletResource
 * @package App\Http\Resources\Wallet\WalletResource
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    public static $wrap = '';

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'info' => $this->info,
            'type_name' => $this->type_name,
            'operation_type' => (int)$this->operation_type,
            'operation_type_name' => $this->operation_type_name,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'status_name_en' => $this->status_name_en,
            'amount' => $this->amount,
            'send_tx_hash' => $this->send_tx_hash,
            'address_to' => $this->address_to,
            'amls' => $this->getAmlData(),
            'aml_risk_score' => $this->aml_risk_score,
            'aml_file_report_link' => $this->aml_file_report_link ? route('wallet.report', $this->address) : '',
            'comment' => $this->comment,
            'has_error' => (bool)$this->has_error,
            'is_test' => (bool)$this->is_test,
            'last_transfer_amount' => $this->last_transfer_amount,
            'ai_check' => $this->getLatestAiCheck(),
            'transactions' => TransactionResource::collection($this->transactions),
        ];
    }

    /**
     * Completed AI AML check for the wallet.
     * @return array|null
     */
    private function getLatestAiCheck(): ?array
    {
        if ($this->is_test || Development::isLocal()) {
            return [
                'text' => 'Dangerous sources detected: Illegal Service 13.8%, above the 7% threshold for critical risk. Risky Exchange 20.2% and Gambling 0.6% are also present, and the overall risk is elevated. Funds should go to BLACK only, with a refund to the sender or refusal to further handle the asset.',
                'wallet_color' => 'BLACK',
                'risk' => 'HIGH',
                'confidence' => 97,
                'service' => 'ChatGPT (OpenAI)',
                'checked_at' => '2026-07-01T11:49:40+00:00',
                'coinkyt' => [
                    'text' => 'According to CoinKYT: the Illegal Service share of 11.2% exceeds the 7% threshold. Risk assessment based on this service alone: BLACK.',
                    'wallet_color' => 'BLACK',
                    'risk' => 'HIGH',
                    'confidence' => 92,
                ],
            ];
        }

        /** @var \App\Models\Wallet\WalletAiAmlCheck|null $check */
        $check = $this->aiAmlChecks()->completed()->first();

        if (!$check) {
            return null;
        }

        return [
            // Records saved before sanitization was added may contain "<" and break the consumer's HTML
            'text' => AiAmlFormatter::plainSummary($check->summary),
            'wallet_color' => $check->wallet_color,
            'risk' => $check->risk,
            'confidence' => $check->confidence,
            'service' => $check->service?->name,
            'checked_at' => $check->created_at?->toIso8601String(),
            'coinkyt' => $this->formatCoinKytVerdict($check->coinkyt_result),
        ];
    }

    /**
     * Separate AI verdict based on CoinKYT only (if it was among the inputs).
     * @return array|null
     */
    private function formatCoinKytVerdict(?array $verdict): ?array
    {
        if (empty($verdict)) {
            return null;
        }

        return [
            'text' => AiAmlFormatter::plainSummary($verdict['summary'] ?? null),
            'wallet_color' => $verdict['wallet_color'] ?? null,
            'risk' => $verdict['risk'] ?? null,
            'confidence' => $verdict['confidence'] ?? null,
        ];
    }

    /**
     * @return array
     */
    private function getAmlData(): array
    {
        $amlData = [];

        $latestTransaction = $this->transactions
            ->sortByDesc('created_at')
            ->first();

        if (!$latestTransaction) {
            return $amlData;
        }

        /**
         * @var Transaction $latestTransaction
         */
        $latestChecks = $latestTransaction->getLatestAmlChecks();

        /**
         * @var \App\Models\Wallet\TransactionAmlCheck $check
         */
        foreach ($latestChecks as $serviceCode => $check) {
            if ($check && $check->isCompleted()) {
                $amlData[$serviceCode] = [
                    'id' => $check->id,
                    'name' => $check->service->name,
                    'code' => $serviceCode,
                    'risk_score' => $check->risk_score_normalize,
                    'report_link' => $check->report_file_url ? route('aml.report.transaction', $check->id) : '',
                    'report_file' => $check->report_file_url,
                    'uid' => $check->uid,
                    'checked_at' => $check->check_finish_at?->toIso8601String(),
                    'signals' => $check->signals,
                    'counterparty' => $check->counterparty,
                ];
            }
        }

        return $amlData;
    }

    /**
     * @return array[]
     * @deprecated
     */
    public static function amlsTestData(): array
    {
        if (!Development::isLocal()) {
            return [];
        }

        $report = 'https://example.com/aml-report/transaction/8034';

        return [
            'amlbot' => [
                'id' => 8034,
                'name' => 'AML Bot',
                'code' => 'amlbot',
                'risk_score' => 0.4,
                'report_link' => $report,
                'report_file' => '',
                'uid' => 'AMB-' . strtoupper(substr(md5('hello'), 0, 12)),
                'checked_at' => now()->subHours(3)->toIso8601String(),
                'signals' => [
                    'low' => ['Exchange' => 8.5, 'P2p Exchange' => 1.5],
                    'middle' => [],
                    'high' => [],
                ],
                'counterparty' => ['Binance' => 70, 'Coinbase' => 30],
            ],
            'well_wallet' => [
                'id' => 8035,
                'name' => 'Well Wallet',
                'code' => 'well_wallet',
                'risk_score' => 0.1,
                'report_link' => 'https://example.com/aml-report/transaction/8035',
                'report_file' => '',
                'uid' => 'WW-' . strtoupper(substr(md5('hello'), 0, 12)),
                'checked_at' => now()->subHours(2)->toIso8601String(),
                'signals' => [
                    'low' => ['Exchange' => 10],
                    'middle' => [],
                    'high' => [],
                ],
                'counterparty' => ['Bybit' => 100],
            ],
            'getblock' => [
                'id' => 8035,
                'name' => 'GetBlock',
                'code' => 'getblock',
                'risk_score' => 0.2,
                'report_link' => $report,
                'report_file' => '',
                'uid' => 'GB-' . strtoupper(substr(md5('hellogb'), 0, 12)),
                'checked_at' => now()->subHour()->toIso8601String(),
                'signals' => [
                    'low' => ['Exchange' => 6.0],
                    'middle' => [],
                    'high' => ['Gambling' => 3.0, 'Scam' => 1.0],
                ],
                'counterparty' => ['Bybit' => 60, 'OKX' => 40],
            ],
        ];
    }
}
