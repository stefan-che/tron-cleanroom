<?php

namespace App\Services\Aml\Elliptic;

class WalletChecker
{
    private EllipticClient $client;

    public function __construct(EllipticClient $client)
    {
        $this->client = $client;
    }

    /**
     * Converts a blockchain name to the Elliptic API format
     */
    private function normalizeBlockchain(string $blockchain): string
    {
        $mapping = [
            'TRX' => 'tron',
            'TRON' => 'tron',
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
        ];

        $key = strtoupper($blockchain);
        return $mapping[$key] ?? 'holistic';
    }

    /**
     * Synchronous address check
     * @param string $address Address to check
     * @param string $asset Asset ticker (e.g. 'TRX', 'BTC', 'ETH')
     * @param string $blockchain Blockchain name
     * @param string|null $customerReference Your tracking reference
     */
    public function checkAddress(string $address, string $asset, string $blockchain, string $customerReference = null)
    {
        $normalizedBlockchain = $this->normalizeBlockchain($blockchain);

        $params = [
            'subject' => [
                'asset' => strtolower($asset), // The API expects lowercase values
                'blockchain' => $blockchain,
                'type' => 'address',
                'hash' => $address,
            ],
            'type' => 'wallet_exposure',
        ];

        if ($customerReference) {
            $params['customer_reference'] = $customerReference;
        }

        return $this->client->checkWalletSync($params);
    }

    /**
     * Asynchronous address check
     */
    public function checkAddressAsync(string $address, string $blockchain = 'holistic', string $customerReference = null)
    {
        $normalizedBlockchain = $this->normalizeBlockchain($blockchain);

        $params = [
            'subject' => [
                'asset' => $normalizedBlockchain,
                'blockchain' => $normalizedBlockchain,
                'type' => 'address',
                'hash' => $address,
            ],
            'type' => 'wallet_exposure',
        ];

        if ($customerReference) {
            $params['customer_reference'] = $customerReference;
        }

        return $this->client->checkWalletAsync($params);
    }

    /**
     * Fetches the result of an asynchronous check
     */
    public function getCheckResult(string $requestId)
    {
        return $this->client->get("/v2/wallet/{$requestId}");

        return $this->client->getWalletResult($requestId);
    }


    /**
     * Returns the result of an asynchronous request
     */
    public function getWalletResult(string $requestId)
    {
        return $this->get("/v2/wallet/result/{$requestId}");
    }

    public function getErrorResponse()
    {
//        "name" => "ValidationError"
//  "message" => "subject.asset and subject.blockchain combination not recognised"
//  "request" => array:2 [
//            "subject" => array:4 [
//            "asset" => "TRX"
//      "blockchain" => "TRX"
//      "type" => "address"
//      "hash" => "TEXAMPLEADDRESS0000000000000000003"
//    ]
//    "type" => "wallet_exposure"
//  ]
//]
    }

    public function getSuceessResponse()
    {
//        Shortened example of a wallet_exposure response (identifiers replaced with placeholders):
//        array:26 [
//  "analysed_by" => ["id" => "<api_key_id>", "type" => "api_key"]
//  "cluster_entities" => [
//      0 => [
//          "name" => "Unknown"
//          "category" => "Unknown"
//          "category_id" => "00000000-0000-4000-8000-500000000001"
//          "actor_id" => -4
//          "entity_id" => "00000000-0000-4000-8000-800000000001"
//          "is_primary_entity" => true
//          "is_vasp" => null
//          "is_after_sanction_date" => false
//      ]
//  ]
//  "id" => "<analysis_id>"
//  "screening_id" => "<screening_id>"
//  "subject" => [
//      "asset" => "holistic"
//      "hash" => "TEXAMPLEADDRESS0000000000000000003"
//      "type" => "address"
//      "blockchain" => "holistic"
//  ]
//  "type" => "wallet_exposure"
//  "customer" => ["id" => null, "reference" => ""]
//  "created_at" => "2024-01-01T00:00:00.000Z"
//  "updated_at" => "2024-01-01T00:00:00.000Z"
//  "analysed_at" => "2024-01-01T00:00:00.000Z"
//  "process_status" => "complete"
//  "process_status_id" => 2
//  "workflow_status_id" => 1
//  "workflow_status" => "active"
//  "error" => null
//  "team_id" => "<team_id>"
//  "asset_tier" => "full"
//  "risk_score" => 0.18
//  "blockchain_info" => ["cluster" => ["inflow_value" => ["usd" => 1000.0], "outflow_value" => ["usd" => 1000.0]]]
//  "risk_score_detail" => ["destination" => 0.02, "source" => 0.18]
//  "evaluation_detail" => [
//      "source" => [
//          0 => [
//              "rule_id" => "<rule_id>"
//              "rule_type" => "exposure"
//              "risk_score" => 0.18
//              "matched_behaviors" => []
//              "matched_elements" => [ ... per-category contributions: category, category_id,
//                  contribution_percentage, indirect_percentage, min_number_of_hops, entities ... ]
//              "rule_name" => "<rule_name>"
//          ]
//      ]
//      "destination" => [ ... same shape ... ]
//  ]
//  "triggered_rules" => []
//  "changes" => []
//  "screening_source" => "sync"
//  "detected_behaviors" => []
//]

    }
}
