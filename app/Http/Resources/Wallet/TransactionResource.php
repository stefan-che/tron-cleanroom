<?php

namespace App\Http\Resources\Wallet;

use App\Helpers\Development;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\TransactionAmlCheck;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class TransactionResource
 * @package App\Http\Resources\Wallet\TransactionResource
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
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
            'tx_id' => $this->tx_id,
            'status' => $this->status,
            'from_address' => $this->from_address,
            'created_at' => $this->created_at->timestamp,
            'status_name' => $this->status_name,
            'status_name_en' => $this->status_name_en,
            'amount' => $this->amount,
            'send_tx_hash' => $this->send_tx_hash,
            'amls' => $this->getAmlData(),
            'signals' => $this->signals_groups,
            'aml_risk_score' => $this->aml_risk_score,
            'aml_file_report_link' => $this->aml_file_report_link ? route('transaction.report', $this->id) : '',
            'counterparty' => $this->getCounterPartyData(),
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
         * @var TransactionAmlCheck $check
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
}
