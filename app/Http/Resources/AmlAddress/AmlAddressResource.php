<?php

namespace App\Http\Resources\AmlAddress;

use App\Helpers\Development;
use App\Http\Resources\Wallet\WalletResource;
use App\Models\Aml\AmlAddress;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class AmlAddressResource
 * @package App\Http\Resources\AmlAddress\AmlAddressResource
 * @mixin AmlAddress
 */
class AmlAddressResource extends JsonResource
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
            'created_at' => $this->created_at,
            'check_finish_at' => $this->check_finish_at,
            'address' => $this->address,
            'is_new_transactions' => $this->isNewTransactions(),
            'status' => $this->status,
            'status_name' => $this->status_name,
            'blockchain' => $this->blockchain_text,
            'amls' => $this->getAmlData(),
            'aml_risk_score' => $this->aml_risk_score,
            'aml_file_report_link' => $this->aml_file_report_link ? route('aml.report', $this->address) : '',
            'response' => $this->response,
            'has_error' => (bool)$this->has_error,
            'is_test' => (bool)$this->is_test,

            'current_tx_count' => $this->current_tx_count,
            'last_check_tx_count' => $this->last_check_tx_count,
            'hashes' => [
                'current_transaction_hash' => $this->current_transaction_hash,
                'last_check_transaction_hash' => $this->last_check_transaction_hash,
            ],
            'trx_hashes' => [
                'last_check_trx_transaction_hash' => $this->last_check_trx_transaction_hash,
                'current_trx_transaction_hash' => $this->current_trx_transaction_hash,
            ],
        ];
    }

    /**
     * @return array
     */
    private function getAmlData(): array
    {
        $amlData = [];

        $latestChecks = $this->getLatestAmlChecks();

        /**
         * @var \App\Models\Aml\AmlAddressCheck $check
         */
        foreach ($latestChecks as $serviceCode => $check) {
            if ($check && $check->isCompleted()) {
                $amlData[$serviceCode] = [
                    'id' => $check->id,
                    'name' => $check->amlService->name,
                    'code' => $serviceCode,
                    'risk_score' => $check->aml_risk_score,
                    'report_link' => $check->aml_file_report_link ? route('aml.report.address', $check->id) : '',
                    'checked_at' => $check->check_finish_at?->toIso8601String(),
                    'signals' => $check->signals,
                    'counterparty' => $check->counterparty,
                ];
            }
        }

        return $amlData;
    }
}
